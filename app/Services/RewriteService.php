<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\RewriteLink;
use App\Models\RewriteLinkUsage;
use App\Models\RewriteLog;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RewriteService
{
    private Site $site;
    private ?AiSetting $settings;
    private string $siteHost;
    private array $allowedTags;
    private array $allowedAttributes;
    private bool $skipExternalLinks;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->settings = AiSetting::first();
        $this->siteHost = parse_url($site->url, PHP_URL_HOST) ?: '';

        // Use site-specific settings
        $this->skipExternalLinks = $site->skip_external_links ?? true;
        $this->allowedTags = $site->allowed_tags
            ? array_map('trim', explode(',', $site->allowed_tags))
            : ['p', 'h2', 'h3', 'h4', 'h5', 'img', 'br', 'li', 'ul', 'ol', 'i', 'em', 'table', 'tr', 'td', 'u', 'th', 'thead', 'tbody'];
        $this->allowedAttributes = $site->allowed_attributes
            ? array_map('trim', explode(',', $site->allowed_attributes))
            : ['src'];
    }

    /**
     * Run the rewrite process for the site.
     * Гарантирует успешный рерайт указанного количества статей (или всех доступных).
     */
    public function run(?int $authorId, ?int $categoryId, ?int $limit): array
    {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'target' => $limit, // Целевое количество успешных рерайтов
        ];

        // Получаем общее количество доступных статей
        $totalAvailable = $this->fetchArticlesCount($authorId, $categoryId);

        if ($totalAvailable === 0) {
            $this->log(null, null, 'skipped', 'Нет статей для обработки');
            return $results;
        }

        // Если limit не указан — обрабатываем все доступные статьи
        $targetSuccessCount = $limit ?? $totalAvailable;
        $results['target'] = $targetSuccessCount;

        // Размер порции для запроса статей
        $batchSize = 20;
        $offset = 0;

        // Множество ID статей, которые уже пытались обработать в этой сессии (чтобы не зациклиться)
        $attemptedArticleIds = [];

        // Максимальное количество итераций для защиты от бесконечного цикла
        $maxIterations = (int) ceil($totalAvailable / $batchSize) + 10;
        $iteration = 0;

        while ($results['processed'] < $targetSuccessCount && $iteration < $maxIterations) {
            $iteration++;

            // Check if stop was requested
            if ($this->shouldStop()) {
                $this->log(null, null, 'skipped', 'Рерайт остановлен пользователем');
                break;
            }

            // Запрашиваем следующую порцию статей
            $articles = $this->fetchArticles($authorId, $categoryId, $batchSize, $offset);

            if (empty($articles)) {
                // Больше статей нет — выходим
                break;
            }

            // Фильтруем статьи, которые уже пытались обработать
            $newArticles = array_filter($articles, function ($article) use ($attemptedArticleIds) {
                return !in_array($article['id'], $attemptedArticleIds);
            });

            if (empty($newArticles)) {
                // Все статьи в этой порции уже пробовали — сдвигаем offset
                $offset += $batchSize;
                continue;
            }

            foreach ($newArticles as $article) {
                // Проверяем, достигли ли целевого количества
                if ($results['processed'] >= $targetSuccessCount) {
                    break 2; // Выходим из обоих циклов
                }

                // Check if stop was requested
                if ($this->shouldStop()) {
                    $this->log(null, null, 'skipped', 'Рерайт остановлен пользователем');
                    break 2;
                }

                // Помечаем статью как попытку обработки
                $attemptedArticleIds[] = $article['id'];

                try {
                    $result = $this->processArticle($article);

                    if ($result === 'processed') {
                        $results['processed']++;
                    } elseif ($result === 'skipped') {
                        $results['skipped']++;
                    } elseif ($result === 'error') {
                        $results['errors']++;
                    }
                } catch (\Exception $e) {
                    $results['errors']++;
                    $this->log(
                        $article['id'] ?? null,
                        $article['title'] ?? null,
                        'error',
                        'Ошибка: ' . $e->getMessage()
                    );
                }
            }

            // Сдвигаем offset для следующей порции
            $offset += $batchSize;

            // Если offset превысил общее количество, но цель не достигнута,
            // значит больше статей нет
            if ($offset >= $totalAvailable) {
                break;
            }
        }

        return $results;
    }

    /**
     * Run the rewrite process for a single article (JS mode).
     * Returns array with status and info for frontend progress tracking.
     */
    public function runOne(?int $authorId, ?int $categoryId, int $offset = 0): array
    {
        $result = [
            'status' => 'done',      // 'processed', 'skipped', 'error', 'done' (no more articles)
            'message' => '',
            'article_id' => null,
            'article_title' => null,
            'has_more' => false,     // есть ли ещё статьи для обработки
            'total' => 0,            // общее количество необработанных статей
        ];

        // Получаем общее количество доступных статей
        $totalAvailable = $this->fetchArticlesCount($authorId, $categoryId);
        $result['total'] = $totalAvailable;

        if ($totalAvailable === 0) {
            $result['status'] = 'done';
            $result['message'] = 'Нет статей для обработки';
            return $result;
        }

        // Запрашиваем одну статью с указанным offset
        try {
            $articles = $this->fetchArticles($authorId, $categoryId, 1, $offset);
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            return $result;
        }

        if (empty($articles)) {
            $result['status'] = 'done';
            $result['message'] = 'Больше статей нет';
            return $result;
        }

        $article = $articles[0];
        $result['article_id'] = $article['id'] ?? null;
        $result['article_title'] = $article['title'] ?? null;

        // Проверяем, есть ли ещё статьи после этой
        $result['has_more'] = $totalAvailable > 1;

        try {
            $processResult = $this->processArticle($article);

            $result['status'] = $processResult; // 'processed', 'skipped', 'error'

            if ($processResult === 'processed') {
                $result['message'] = 'Статья успешно обработана';
            } elseif ($processResult === 'skipped') {
                $result['message'] = 'Статья пропущена';
            } else {
                $result['message'] = 'Ошибка при обработке статьи';
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Ошибка: ' . $e->getMessage();
            $this->log(
                $article['id'] ?? null,
                $article['title'] ?? null,
                'error',
                'Ошибка: ' . $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Fetch total count of available articles from Joomla API.
     */
    private function fetchArticlesCount(?int $authorId, ?int $categoryId): int
    {
        $endpoint = rtrim($this->site->url, '/') . '/index.php';

        $params = [
            'option' => 'com_api',
            'task' => 'articles_count',
            'onlyUnprocessed' => 1,
        ];

        if ($authorId) {
            $params['author'] = $authorId;
        }

        if ($categoryId) {
            $params['category'] = $categoryId;
        }

        $response = $this->makeJoomlaRequest('GET', $endpoint, $params);

        if (!$response || ($response['status'] ?? null) !== 'ok') {
            return 0;
        }

        return (int) ($response['count'] ?? 0);
    }

    /**
     * Fetch articles from Joomla API with pagination.
     */
    private function fetchArticles(?int $authorId, ?int $categoryId, ?int $limit, int $offset = 0): array
    {
        $endpoint = rtrim($this->site->url, '/') . '/index.php';

        $params = [
            'option' => 'com_api',
            'task' => 'articles',
            'onlyUnprocessed' => 1,
        ];

        if ($authorId) {
            $params['author'] = $authorId;
        }

        if ($categoryId) {
            $params['category'] = $categoryId;
        }

        if ($limit) {
            $params['limit'] = $limit;
        }

        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        $response = $this->makeJoomlaRequest('GET', $endpoint, $params);

        if (!$response || ($response['status'] ?? null) !== 'ok') {
            throw new \Exception('Не удалось получить список статей с сайта');
        }

        return $response['articles'] ?? [];
    }

    /**
     * Process a single article.
     */
    private function processArticle(array $articleMeta): string
    {
        $articleId = $articleMeta['id'];
        $articleTitle = $articleMeta['title'] ?? '';

        // Fetch full article content
        $article = $this->fetchArticle($articleId);

        if (!$article) {
            $this->log($articleId, $articleTitle, 'error', 'Не удалось получить содержимое статьи');
            return 'error';
        }

        $fulltext = $article['fulltext'] ?? '';
        $introtext = $article['introtext'] ?? '';
        $content = $introtext . $fulltext;
        $originalContent = $content; // Сохраняем оригинал для логов

        // Check for external links (if enabled)
        if ($this->skipExternalLinks && $this->hasExternalLinks($content)) {
            $this->log($articleId, $articleTitle, 'skipped', 'Статья содержит внешние ссылки (рекламная)');
            $this->markArticleProcessed($articleId);
            return 'skipped';
        }

        // Clean HTML
        $cleanedContent = $this->cleanHtml($content);
        $cleanedTitle = strip_tags($articleTitle);

        // Get rewrite from AI (returns array with title, description, body)
        $rewriteResult = $this->rewriteWithAi($cleanedTitle, $cleanedContent);

        if (!$rewriteResult) {
            $this->log($articleId, $articleTitle, 'error', 'Ошибка при рерайте через ИИ');
            return 'error';
        }

        $newTitle = $rewriteResult['title'];
        $newDescription = $rewriteResult['description'];
        $newBody = $rewriteResult['body'];

        // Add interlinking to body
        $newBody = $this->addInterlinking($newBody, $articleId);

        // Формируем результат для логов (с учётом перелинковки)
        $finalResult = [
            'title' => $newTitle,
            'description' => $newDescription,
            'body' => $newBody,
        ];
        // JSON_UNESCAPED_SLASHES чтобы не экранировать слеши в URL
        $rewrittenContent = json_encode($finalResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        // Update article on Joomla
        $updated = $this->updateArticle($articleId, $newTitle, $newBody, $newDescription);

        if (!$updated) {
            $this->log($articleId, $articleTitle, 'error', 'Не удалось обновить статью на сайте', $originalContent, $cleanedContent, $rewrittenContent);
            return 'error';
        }

        // Mark as processed
        $this->markArticleProcessed($articleId);

        $this->log($articleId, $articleTitle, 'success', 'Статья успешно обработана', $originalContent, $cleanedContent, $rewrittenContent);

        return 'processed';
    }

    /**
     * Fetch single article content.
     */
    private function fetchArticle(int $articleId): ?array
    {
        $endpoint = rtrim($this->site->url, '/') . '/index.php';

        $response = $this->makeJoomlaRequest('GET', $endpoint, [
            'option' => 'com_api',
            'task' => 'article',
            'id' => $articleId,
        ]);

        if (!$response || ($response['status'] ?? null) !== 'ok') {
            return null;
        }

        return $response;
    }

    /**
     * Check if content has external links.
     */
    private function hasExternalLinks(string $content): bool
    {
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (empty($matches[1])) {
            return false;
        }

        foreach ($matches[1] as $href) {
            // Skip anchors and relative URLs
            if (str_starts_with($href, '#') || str_starts_with($href, '/') || str_starts_with($href, '?')) {
                continue;
            }

            // Skip mailto and tel
            if (str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $linkHost = parse_url($href, PHP_URL_HOST);

            if ($linkHost && $linkHost !== $this->siteHost && !str_ends_with($linkHost, '.' . $this->siteHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean HTML content, keeping only allowed tags and attributes.
     */
    private function cleanHtml(string $content): string
    {
        // Build allowed tags string for strip_tags
        $allowedTagsStr = '<' . implode('><', $this->allowedTags) . '>';

        // Strip disallowed tags
        $content = strip_tags($content, $allowedTagsStr);

        // Remove disallowed attributes using DOMDocument for better parsing
        if (empty($this->allowedAttributes)) {
            // Если нет разрешённых атрибутов, удаляем все
            $content = preg_replace('/<([a-z][a-z0-9]*)\s+[^>]*>/i', '<$1>', $content);
            return trim($content);
        }

        $allowedAttributes = $this->allowedAttributes;

        // Обрабатываем теги с атрибутами
        $content = preg_replace_callback(
            '/<([a-z][a-z0-9]*)(\s+[^>]*)>/i',
            function ($matches) use ($allowedAttributes) {
                $tag = $matches[1];
                $attrsString = $matches[2];

                // Парсим атрибуты более надёжным способом
                // Поддерживаем: attr="value", attr='value', attr=value, attr
                preg_match_all('/\s+([a-z\-_]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]*)))?/i', $attrsString, $attrMatches, PREG_SET_ORDER);

                $cleanAttrs = [];
                foreach ($attrMatches as $attr) {
                    $attrName = strtolower($attr[1]);
                    if (in_array($attrName, $allowedAttributes)) {
                        // Получаем значение атрибута
                        $value = $attr[2] ?? $attr[3] ?? $attr[4] ?? null;
                        if ($value !== null) {
                            $cleanAttrs[] = $attrName . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                        } else {
                            $cleanAttrs[] = $attrName;
                        }
                    }
                }

                if (empty($cleanAttrs)) {
                    return "<{$tag}>";
                }

                return "<{$tag} " . implode(' ', $cleanAttrs) . ">";
            },
            $content
        );

        // Убираем лишние пробелы и переносы строк
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/>\s+</', '><', $content);

        return trim($content);
    }

    /**
     * Estimate token count (rough approximation: 1 token ≈ 4 chars for Russian text)
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 3);
    }

    /**
     * Split content into chunks by paragraphs, respecting max token limit.
     */
    private function splitContentIntoChunks(string $content, int $maxTokensPerChunk = 2000): array
    {
        // Разбиваем по параграфам (</p>, <br>, переносы строк)
        $paragraphs = preg_split('/(<\/p>|<br\s*\/?>|\n\n)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraphTokens = $this->estimateTokens($paragraph);

            // Если один параграф слишком большой, разбиваем его по предложениям
            if ($paragraphTokens > $maxTokensPerChunk) {
                if ($currentChunk) {
                    $chunks[] = $currentChunk;
                    $currentChunk = '';
                    $currentTokens = 0;
                }

                // Разбиваем большой параграф по предложениям
                $sentences = preg_split('/(?<=[.!?])\s+/u', $paragraph);
                foreach ($sentences as $sentence) {
                    $sentenceTokens = $this->estimateTokens($sentence);
                    if ($currentTokens + $sentenceTokens > $maxTokensPerChunk && $currentChunk) {
                        $chunks[] = $currentChunk;
                        $currentChunk = $sentence;
                        $currentTokens = $sentenceTokens;
                    } else {
                        $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
                        $currentTokens += $sentenceTokens;
                    }
                }
                continue;
            }

            if ($currentTokens + $paragraphTokens > $maxTokensPerChunk && $currentChunk) {
                $chunks[] = $currentChunk;
                $currentChunk = $paragraph;
                $currentTokens = $paragraphTokens;
            } else {
                $currentChunk .= $paragraph;
                $currentTokens += $paragraphTokens;
            }
        }

        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks ?: [$content];
    }

    /**
     * Rewrite content using Deepseek AI with streaming support.
     * Returns array with keys: title, description, body
     */
    private function rewriteWithAi(string $title, string $content): ?array
    {
        if (!$this->settings || !$this->settings->deepseek_api) {
            throw new \Exception('API ключ Deepseek не настроен');
        }

        if (!$this->settings->prompt) {
            throw new \Exception('Промпт не настроен');
        }

        $contentTokens = $this->estimateTokens($content);
        $maxChunkTokens = 2500; // Максимум токенов на чанк

        // Если статья короткая, обрабатываем целиком
        if ($contentTokens <= $maxChunkTokens) {
            return $this->rewriteSingleChunk($title, $content, true);
        }

        // Разбиваем длинную статью на части
        $chunks = $this->splitContentIntoChunks($content, $maxChunkTokens);

        // Сначала получаем новый заголовок и description на основе первого чанка
        $firstResult = $this->rewriteSingleChunk($title, $chunks[0], true);

        if (!$firstResult) {
            throw new \Exception('Не удалось переписать первую часть статьи');
        }

        $newTitle = $firstResult['title'];
        $newDescription = $firstResult['description'];
        $rewrittenParts = [$firstResult['body']];

        // Переписываем остальные части (только body)
        for ($i = 1; $i < count($chunks); $i++) {
            $chunkResult = $this->rewriteSingleChunk($title, $chunks[$i], false);
            if ($chunkResult && !empty($chunkResult['body'])) {
                $rewrittenParts[] = $chunkResult['body'];
            } else {
                // Если не удалось переписать часть, используем оригинал
                $rewrittenParts[] = $chunks[$i];
            }
        }

        return [
            'title' => $newTitle,
            'description' => $newDescription,
            'body' => implode("\n\n", $rewrittenParts),
        ];
    }

    /**
     * Rewrite a single chunk of content using Deepseek AI with streaming.
     */
    private function rewriteSingleChunk(string $title, string $content, bool $includeTitle): ?array
    {
        $prompt = $this->settings->prompt;
        $temperature = $this->settings->temperature ?? 0.7;

        if ($includeTitle) {
            $userMessage = "Заголовок: {$title}\n\nТекст статьи:\n{$content}";
        } else {
            // Для последующих чанков просим только переписать текст
            $userMessage = "Перепиши следующий текст в том же стиле, сохраняя смысл. Верни только переписанный текст без JSON:\n\n{$content}";
        }

        $maxAttempts = 5;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                // Используем streaming для избежания таймаутов
                $aiContent = $this->callDeepseekWithStreaming(
                    $prompt,
                    $userMessage,
                    $temperature,
                    $includeTitle ? 4096 : 3000 // max_tokens
                );

                if (!$aiContent) {
                    throw new \Exception('Пустой ответ от API');
                }

                if ($includeTitle) {
                    // Парсим JSON из ответа AI
                    return $this->parseAiJsonResponse($aiContent);
                } else {
                    // Для чанков без заголовка возвращаем просто текст
                    return [
                        'title' => '',
                        'description' => '',
                        'body' => trim($aiContent),
                    ];
                }
            } catch (\Exception $e) {
                $lastException = $e;

                // Экспоненциальная задержка перед повторной попыткой
                if ($attempt < $maxAttempts) {
                    $delay = min(pow(2, $attempt) * 1000, 10000); // 2s, 4s, 8s, 10s max
                    usleep($delay * 1000);
                    continue;
                }
            }
        }

        throw new \Exception('Ошибка API Deepseek после ' . $maxAttempts . ' попыток: ' . ($lastException ? $lastException->getMessage() : 'неизвестная ошибка'));
    }

    /**
     * Call Deepseek API with streaming to avoid timeouts.
     */
    private function callDeepseekWithStreaming(string $systemPrompt, string $userMessage, float $temperature, int $maxTokens): string
    {
        $apiKey = $this->settings->deepseek_api;

        // Используем cURL для streaming
        $ch = curl_init();

        $postData = json_encode([
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => true,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.deepseek.com/chat/completions',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300, // 5 минут общий таймаут
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 60,
            CURLOPT_TCP_KEEPINTVL => 30,
        ]);

        $fullContent = '';
        $buffer = '';

        // Callback для обработки streaming данных
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullContent, &$buffer) {
            $buffer .= $data;

            // Обрабатываем SSE события
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);

                // Пропускаем пустые строки и keep-alive
                if (empty($line) || $line === ': keep-alive') {
                    continue;
                }

                // Парсим SSE данные
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);

                    if ($jsonStr === '[DONE]') {
                        continue;
                    }

                    $json = json_decode($jsonStr, true);
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        $fullContent .= $json['choices'][0]['delta']['content'];
                    }
                }
            }

            return strlen($data);
        });

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL ошибка: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \Exception('HTTP ошибка: ' . $httpCode);
        }

        return $fullContent;
    }

    /**
     * Parse JSON response from AI, handling markdown code blocks.
     */
    private function parseAiJsonResponse(string $aiContent): array
    {
        // Удаляем возможные markdown блоки кода
        $jsonContent = $aiContent;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $aiContent, $matches)) {
            $jsonContent = trim($matches[1]);
        }

        $parsed = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($parsed)) {
            throw new \Exception('Не удалось распарсить JSON ответ от AI: ' . json_last_error_msg());
        }

        // Проверяем наличие обязательных полей
        if (empty($parsed['title']) || empty($parsed['body'])) {
            throw new \Exception('AI вернул неполный JSON (отсутствует title или body)');
        }

        return [
            'title' => $parsed['title'],
            'description' => $parsed['description'] ?? '',
            'body' => $parsed['body'],
        ];
    }

    /**
     * Add interlinking to content using streaming API.
     */
    private function addInterlinking(string $content, int $articleId): string
    {
        $link = $this->getAvailableLink();

        if (!$link) {
            return $content;
        }

        // Record usage
        RewriteLinkUsage::create([
            'site_id' => $this->site->id,
            'rewrite_link_id' => $link->id,
            'article_joomla_id' => $articleId,
        ]);

        // Add link to content via additional AI request with streaming
        $linkPrompt = "Впиши в основной текст органично этот URL \"{$link->url}\" в виде гиперссылки. Верни только обновлённый текст без пояснений.";

        try {
            $newContent = $this->callDeepseekWithStreaming(
                $linkPrompt,
                $content,
                0.5,
                4096
            );

            if ($newContent) {
                return $newContent;
            }
        } catch (\Exception $e) {
            // If interlinking fails, return original content
        }

        return $content;
    }

    /**
     * Get an available link for interlinking.
     */
    private function getAvailableLink(): ?RewriteLink
    {
        $domainLimit = $this->settings->domain_usage_limit ?? 1;

        // Get domains already used on this site with their usage count
        $usedDomains = RewriteLinkUsage::query()
            ->where('site_id', $this->site->id)
            ->join('rewrite_links', 'rewrite_links.id', '=', 'rewrite_link_usages.rewrite_link_id')
            ->select('rewrite_links.domain', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('rewrite_links.domain')
            ->having('usage_count', '>=', $domainLimit)
            ->pluck('domain')
            ->toArray();

        // Get links already used on this site
        $usedLinkIds = RewriteLinkUsage::query()
            ->where('site_id', $this->site->id)
            ->pluck('rewrite_link_id')
            ->toArray();

        // Find available link
        $query = RewriteLink::query()
            ->whereNotIn('id', $usedLinkIds);

        if (!empty($usedDomains)) {
            $query->whereNotIn('domain', $usedDomains);
        }

        // Exclude links from the same domain as current site
        if ($this->siteHost) {
            $query->where('domain', '!=', $this->siteHost);
        }

        return $query->inRandomOrder()->first();
    }

    /**
     * Update article on Joomla.
     */
    private function updateArticle(int $articleId, string $title, string $body, string $description = ''): bool
    {
        $endpoint = rtrim($this->site->url, '/') . '/index.php';

        $data = [
            'title' => $title,
            'introtext' => $body,
        ];

        // Добавляем description если есть
        if (!empty($description)) {
            $data['metadesc'] = $description;
        }

        $response = $this->makeJoomlaRequest('POST', $endpoint . '?option=com_api&task=article_update&id=' . $articleId, $data, true);

        return $response && ($response['status'] ?? null) === 'ok';
    }

    /**
     * Mark article as processed on Joomla.
     */
    private function markArticleProcessed(int $articleId): bool
    {
        $endpoint = rtrim($this->site->url, '/') . '/index.php';

        $response = $this->makeJoomlaRequest('POST', $endpoint . '?option=com_api&task=article_mark_processed&id=' . $articleId, [], true);

        return $response && ($response['status'] ?? null) === 'ok';
    }

    /**
     * Make request to Joomla API.
     */
    private function makeJoomlaRequest(string $method, string $url, array $data = [], bool $isJson = false): ?array
    {
        $apiKey = env('JOOMLA_API_KEY');

        $client = Http::timeout(30);

        if (!empty($apiKey)) {
            $client = $client->withHeaders([
                'X-Api-Key' => $apiKey,
            ]);
        }

        try {
            if ($method === 'GET') {
                $response = $client->get($url, $data);
            } else {
                if ($isJson) {
                    $response = $client->asJson()->post($url, $data);
                } else {
                    $response = $client->post($url, $data);
                }
            }

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            // Log error
        }

        return null;
    }

    /**
     * Check if stop was requested for this site.
     */
    private function shouldStop(): bool
    {
        return Cache::get("rewrite_stop_{$this->site->id}", false) === true;
    }

    /**
     * Log rewrite action.
     */
    private function log(?int $articleId, ?string $title, string $status, string $message, ?string $originalContent = null, ?string $cleanedContent = null, ?string $rewrittenContent = null): void
    {
        RewriteLog::create([
            'site_id' => $this->site->id,
            'article_joomla_id' => $articleId,
            'article_title' => $title,
            'status' => $status,
            'message' => $message,
            'original_content' => $originalContent,
            'cleaned_content' => $cleanedContent,
            'rewritten_content' => $rewrittenContent,
        ]);
    }
}
