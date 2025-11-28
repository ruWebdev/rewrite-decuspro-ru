<?php

namespace App\Http\Controllers;

use App\Models\RewriteLink;
use App\Models\RewriteLog;
use App\Models\Site;
use App\Models\SiteAuthor;
use App\Models\SiteCategory;
use App\Services\RewriteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RewriteController extends Controller
{
    /**
     * Show rewrite page for the site.
     */
    public function show(Site $site)
    {
        $authors = SiteAuthor::query()
            ->where('site_id', $site->id)
            ->orderBy('name')
            ->get(['id', 'joomla_id', 'name', 'username']);

        $categories = SiteCategory::query()
            ->where('site_id', $site->id)
            ->orderBy('title')
            ->get(['id', 'joomla_id', 'title']);

        $logs = RewriteLog::query()
            ->where('site_id', $site->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->select([
                'id',
                'site_id',
                'article_joomla_id',
                'article_title',
                'status',
                'message',
                'created_at',
            ])
            ->selectRaw('original_content IS NOT NULL as has_original_content')
            ->selectRaw('cleaned_content IS NOT NULL as has_cleaned_content')
            ->selectRaw('rewritten_content IS NOT NULL as has_rewritten_content')
            ->get();

        $links = RewriteLink::query()
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('SiteRewrite', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'skip_external_links' => $site->skip_external_links,
                'allowed_tags' => $site->allowed_tags,
                'allowed_attributes' => $site->allowed_attributes,
            ],
            'authors' => $authors,
            'categories' => $categories,
            'logs' => $logs,
            'links' => $links,
        ]);
    }

    /**
     * Run the rewrite process.
     */
    public function run(Request $request, Site $site)
    {
        $validated = $request->validate([
            'author_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        // Clear stop flag before starting
        Cache::forget("rewrite_stop_{$site->id}");

        $service = new RewriteService($site);

        $results = $service->run(
            $validated['author_id'] ?? null,
            $validated['category_id'] ?? null,
            $validated['limit'] ?? null
        );

        return redirect()
            ->route('sites.rewrite', $site)
            ->with('results', $results);
    }

    /**
     * Stop the rewrite process.
     */
    public function stop(Site $site)
    {
        Cache::put("rewrite_stop_{$site->id}", true, 300); // 5 minutes TTL

        return response()->json(['status' => 'stopped']);
    }

    /**
     * Rewrite a single article (for JS mode).
     * Returns JSON with result status.
     */
    public function rewriteOne(Request $request, Site $site)
    {
        $validated = $request->validate([
            'author_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $service = new RewriteService($site);

        $result = $service->runOne(
            $validated['author_id'] ?? null,
            $validated['category_id'] ?? null,
            $validated['offset'] ?? 0
        );

        return response()->json($result);
    }

    /**
     * Store a new rewrite link.
     */
    public function storeLink(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'anchor' => ['nullable', 'string', 'max:255'],
        ]);

        $domain = parse_url($validated['url'], PHP_URL_HOST) ?: '';

        RewriteLink::create([
            'url' => $validated['url'],
            'domain' => $domain,
            'anchor' => $validated['anchor'] ?? null,
        ]);

        return redirect()->back();
    }

    /**
     * Delete a rewrite link.
     */
    public function destroyLink(RewriteLink $link)
    {
        $link->delete();

        return redirect()->back();
    }

    /**
     * Import links from text (one URL per line).
     */
    public function importLinks(Request $request)
    {
        $validated = $request->validate([
            'urls' => ['required', 'string'],
        ]);

        $lines = explode("\n", $validated['urls']);
        $imported = 0;

        foreach ($lines as $line) {
            $url = trim($line);

            if (empty($url)) {
                continue;
            }

            // Basic URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $domain = parse_url($url, PHP_URL_HOST) ?: '';

            // Check if already exists
            if (RewriteLink::where('url', $url)->exists()) {
                continue;
            }

            RewriteLink::create([
                'url' => $url,
                'domain' => $domain,
            ]);

            $imported++;
        }

        return redirect()->back()->with('imported', $imported);
    }

    /**
     * Update site settings.
     */
    public function updateSettings(Request $request, Site $site)
    {
        $validated = $request->validate([
            'skip_external_links' => ['boolean'],
            'allowed_tags' => ['nullable', 'string'],
            'allowed_attributes' => ['nullable', 'string'],
        ]);

        $site->update($validated);

        return redirect()->route('sites.rewrite', $site);
    }

    /**
     * Clear all logs for a site.
     */
    public function clearLogs(Site $site)
    {
        RewriteLog::where('site_id', $site->id)->delete();

        return redirect()->route('sites.rewrite', $site);
    }

    /**
     * Return log content on demand (for modal view).
     */
    public function showLog(Request $request, RewriteLog $log)
    {
        $type = $request->query('type', 'original');

        $columnMap = [
            'original' => 'original_content',
            'cleaned' => 'cleaned_content',
            'rewritten' => 'rewritten_content',
        ];

        $column = $columnMap[$type] ?? $columnMap['original'];

        return response()->json([
            'id' => $log->id,
            'type' => $type,
            'content' => $log->{$column},
        ]);
    }
}
