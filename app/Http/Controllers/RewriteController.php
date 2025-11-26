<?php

namespace App\Http\Controllers;

use App\Models\RewriteLink;
use App\Models\RewriteLog;
use App\Models\Site;
use App\Models\SiteAuthor;
use App\Models\SiteCategory;
use App\Services\RewriteService;
use Illuminate\Http\Request;
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
            ->get();

        $links = RewriteLink::query()
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('SiteRewrite', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
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
     * Clear all logs for a site.
     */
    public function clearLogs(Site $site)
    {
        RewriteLog::where('site_id', $site->id)->delete();

        return redirect()->route('sites.rewrite', $site);
    }
}
