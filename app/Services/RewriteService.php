<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\RewriteLink;
use App\Models\RewriteLinkUsage;
use App\Models\RewriteLog;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RewriteService
{
    private const ALLOWED_TAGS = ['p', 'h2', 'h3', 'h4', 'h5', 'img', 'br', 'li', 'ul', 'ol', 'i', 'em', 'table', 'tr', 'td', 'u', 'th', 'thead', 'tbody'];
    private const ALLOWED_ATTRIBUTES = ['src'];

    private Site $site;
    private ?AiSetting $settings;
    private string $siteHost;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->settings = AiSetting::first();
        $this->siteHost = parse_url($site->url, PHP_URL_HOST) ?: '';
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

        // Get articles from Joomla
        $articles = $this->fetchArticles($authorId, $categoryId, $limit);

        if (empty($articles)) {
            $this->log(null, null, 'skipped', 'Нет статей для обработки');
            return $results;
        }

        foreach ($articles as $article) {
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

        // Check for external links
        if ($this->hasExternalLinks($content)) {
            $this->log($articleId, $articleTitle, 'skipped', 'Статья содержит внешние ссылки (рекламная)');
            $this->markArticleProcessed($articleId);
            return 'skipped';
        }

        // Clean HTML
        $cleanedContent = $this->cleanHtml($content);
        $cleanedTitle = strip_tags($articleTitle);

        // Get rewrite from AI
        $rewrittenContent = $this->rewriteWithAi($cleanedTitle, $cleanedContent);

        if (!$rewrittenContent) {
            $this->log($articleId, $articleTitle, 'error', 'Ошибка при рерайте через ИИ');
            return 'error';
        }

        // Add interlinking
        $rewrittenContent = $this->addInterlinking($rewrittenContent, $articleId);

        // Update article on Joomla
        $updated = $this->updateArticle($articleId, $cleanedTitle, $rewrittenContent);

        if (!$updated) {
            $this->log($articleId, $articleTitle, 'error', 'Не удалось обновить статью на сайте');
            return 'error';
        }

        // Mark as processed
        $this->markArticleProcessed($articleId);

        $this->log($articleId, $articleTitle, 'success', 'Статья успешно обработана');

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
        // Build allowed tags string
        $allowedTagsStr = '<' . implode('><', self::ALLOWED_TAGS) . '>';

        // Strip disallowed tags
        $content = strip_tags($content, $allowedTagsStr);

        // Remove disallowed attributes
        $content = preg_replace_callback(
            '/<([a-z][a-z0-9]*)\s+([^>]*)>/i',
            function ($matches) {
                $tag = $matches[1];
                $attrs = $matches[2];

                // Parse attributes and keep only allowed ones
                preg_match_all('/([a-z\-]+)=["\']([^"\']*)["\']|([a-z\-]+)/i', $attrs, $attrMatches, PREG_SET_ORDER);

                $cleanAttrs = [];
                foreach ($attrMatches as $attr) {
                    $attrName = strtolower($attr[1] ?: $attr[3]);
                    if (in_array($attrName, self::ALLOWED_ATTRIBUTES)) {
                        $cleanAttrs[] = $attr[0];
                    }
                }

                if (empty($cleanAttrs)) {
                    return "<{$tag}>";
                }

                return "<{$tag} " . implode(' ', $cleanAttrs) . ">";
            },
            $content
        );

        return trim($content);
    }

    /**
     * Rewrite content using Deepseek AI.
     */
    private function rewriteWithAi(string $title, string $content): ?string
    {
        if (!$this->settings || !$this->settings->deepseek_api) {
            throw new \Exception('API ключ Deepseek не настроен');
        }

        if (!$this->settings->prompt) {
            throw new \Exception('Промпт не настроен');
        }

        $prompt = $this->settings->prompt;

        $userMessage = "Заголовок: {$title}\n\nТекст статьи:\n{$content}";

        $response = Http::timeout(120)
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
                'temperature' => 0.7,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Ошибка API Deepseek: ' . $response->status());
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? null;
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
    private function updateArticle(int $articleId, string $title, string $content): bool
    {
        $endpoint = rtrim($this->site->url, '/') . '/index.php';

        $response = $this->makeJoomlaRequest('POST', $endpoint . '?option=com_api&task=article_update&id=' . $articleId, [
            'title' => $title,
            'fulltext' => $content,
        ], true);

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
     * Log rewrite action.
     */
    private function log(?int $articleId, ?string $title, string $status, string $message): void
    {
        RewriteLog::create([
            'site_id' => $this->site->id,
            'article_joomla_id' => $articleId,
            'article_title' => $title,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
