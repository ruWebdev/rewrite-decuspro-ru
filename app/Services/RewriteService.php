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
     */
    public function run(?int $authorId, ?int $categoryId, ?int $limit): array
    {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // Get IDs of already processed articles for this site
        $processedArticleIds = RewriteLog::query()
            ->where('site_id', $this->site->id)
            ->where('status', 'success')
            ->whereNotNull('article_joomla_id')
            ->pluck('article_joomla_id')
            ->toArray();

        // Get articles from Joomla
        $articles = $this->fetchArticles($authorId, $categoryId, $limit);

        if (empty($articles)) {
            $this->log(null, null, 'skipped', 'Нет статей для обработки');
            return $results;
        }

        // Filter out already processed articles
        $articles = array_filter($articles, function ($article) use ($processedArticleIds) {
            return !in_array($article['id'], $processedArticleIds);
        });

        if (empty($articles)) {
            $this->log(null, null, 'skipped', 'Все статьи уже обработаны');
            return $results;
        }

        foreach ($articles as $article) {
            // Check if stop was requested
            if ($this->shouldStop()) {
                $this->log(null, null, 'skipped', 'Рерайт остановлен пользователем');
                break;
            }

            try {
                $result = $this->processArticle($article);

                if ($result === 'processed') {
                    $results['processed']++;
                } elseif ($result === 'skipped') {
                    $results['skipped']++;
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

        return $results;
    }

    /**
     * Fetch articles from Joomla API.
     */
    private function fetchArticles(?int $authorId, ?int $categoryId, ?int $limit): array
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
     * Rewrite content using Deepseek AI.
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

        $prompt = $this->settings->prompt;

        $userMessage = "Заголовок: {$title}\n\nТекст статьи:\n{$content}";

        $temperature = $this->settings->temperature ?? 0.7;

        // Повторяем запрос к DeepSeek при 504/5xx и сетевых ошибках
        $maxAttempts = 3;
        $response = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->settings->deepseek_api,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.deepseek.com/chat/completions', [
                        'model' => 'deepseek-chat',
                        'messages' => [
                            ['role' => 'system', 'content' => $prompt],
                            ['role' => 'user', 'content' => $userMessage],
                        ],
                        'temperature' => (float) $temperature,
                    ]);

                if ($response->successful()) {
                    // Успешный ответ, выходим из цикла
                    break;
                }

                $status = $response->status();

                // При 504 и других 5xx пробуем ещё раз (если остались попытки)
                if (($status === 504 || $status >= 500) && $attempt < $maxAttempts) {
                    continue;
                }

                // Неретриабельная ошибка или последняя попытка
                throw new \Exception('Ошибка API Deepseek: ' . $status);
            } catch (\Exception $e) {
                $lastException = $e;

                // На последней попытке пробрасываем исключение
                if ($attempt >= $maxAttempts) {
                    throw new \Exception('Ошибка API Deepseek: ' . $e->getMessage(), 0, $e);
                }

                // Иначе продолжаем цикл (повторная попытка)
                continue;
            }
        }

        if (!$response || !$response->successful()) {
            if ($lastException) {
                throw new \Exception('Ошибка API Deepseek: ' . $lastException->getMessage(), 0, $lastException);
            }

            throw new \Exception('Ошибка API Deepseek: неизвестная ошибка');
        }

        $data = $response->json();
        $aiContent = $data['choices'][0]['message']['content'] ?? null;

        if (!$aiContent) {
            return null;
        }

        // Парсим JSON из ответа AI
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
     * Add interlinking to content.
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

        // Add link to content via additional AI request
        $linkPrompt = "Впиши в основной текст органично этот URL \"{$link->url}\" в виде гиперссылки. Верни только обновлённый текст без пояснений.";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->settings->deepseek_api,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.deepseek.com/chat/completions', [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => $linkPrompt],
                        ['role' => 'user', 'content' => $content],
                    ],
                    'temperature' => 0.5,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $newContent = $data['choices'][0]['message']['content'] ?? null;

                if ($newContent) {
                    return $newContent;
                }
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
