<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteAuthor;
use App\Models\SiteCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class SiteController extends Controller
{
    /**
     * Display a listing of the sites.
     */
    public function index()
    {
        $sites = Site::query()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'url']);

        return Inertia::render('Rewrite', [
            'sites' => $sites,
        ]);
    }

    /**
     * Store a newly created site.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255'],
        ]);

        Site::create($validated);

        return redirect()->route('rewrite');
    }

    /**
     * Display the specified site.
     */
    public function show(Site $site)
    {
        return Inertia::render('Site', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
            ],
        ]);
    }

    /**
     * Remove the specified site.
     */
    public function destroy(Site $site)
    {
        $site->delete();

        return redirect()->route('rewrite');
    }

    /**
     * Show authors page for the site.
     */
    public function authors(Site $site)
    {
        $authors = SiteAuthor::query()
            ->where('site_id', $site->id)
            ->orderBy('name')
            ->get(['id', 'joomla_id', 'name', 'username']);

        return Inertia::render('SiteAuthors', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
            ],
            'authors' => $authors,
        ]);
    }

    /**
     * Show categories page for the site.
     */
    public function categories(Site $site)
    {
        $categories = SiteCategory::query()
            ->where('site_id', $site->id)
            ->orderBy('title')
            ->get(['id', 'joomla_id', 'title', 'alias', 'path', 'parent_id', 'level']);

        return Inertia::render('SiteCategories', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Sync authors from remote Joomla site for the given site.
     */
    public function syncAuthors(Site $site)
    {
        $baseUrl = rtrim($site->url, '/');
        $endpoint = $baseUrl . '/index.php';

        $apiKey = env('JOOMLA_API_KEY');
        $client = Http::timeout(15);

        if (!empty($apiKey)) {
            $client = $client->withHeaders([
                'X-Api-Key' => $apiKey,
            ]);
        }

        $response = $client->get($endpoint, [
            'option' => 'com_api',
            'task' => 'getusers',
        ]);

        if (!$response->successful()) {
            return redirect()
                ->route('sites.authors', $site)
                ->with('error', 'Не удалось получить авторов с Joomla');
        }

        $data = $response->json();

        if (!is_array($data) || ($data['status'] ?? null) !== 'ok') {
            return redirect()
                ->route('sites.authors', $site)
                ->with('error', 'Некорректный ответ при получении авторов');
        }

        $users = $data['users'] ?? [];

        SiteAuthor::where('site_id', $site->id)->delete();

        foreach ($users as $user) {
            SiteAuthor::create([
                'site_id' => $site->id,
                'joomla_id' => $user['id'] ?? 0,
                'name' => $user['name'] ?? '',
                'username' => $user['username'] ?? '',
            ]);
        }

        return redirect()->route('sites.authors', $site);
    }

    /**
     * Sync categories from remote Joomla site for the given site.
     */
    public function syncCategories(Site $site)
    {
        $baseUrl = rtrim($site->url, '/');
        $endpoint = $baseUrl . '/index.php';

        $apiKey = env('JOOMLA_API_KEY');
        $client = Http::timeout(15);

        if (!empty($apiKey)) {
            $client = $client->withHeaders([
                'X-Api-Key' => $apiKey,
            ]);
        }

        $response = $client->get($endpoint, [
            'option' => 'com_api',
            'task' => 'getcategories',
        ]);

        if (!$response->successful()) {
            return redirect()
                ->route('sites.categories', $site)
                ->with('error', 'Не удалось получить категории с Joomla');
        }

        $data = $response->json();

        if (!is_array($data) || ($data['status'] ?? null) !== 'ok') {
            return redirect()
                ->route('sites.categories', $site)
                ->with('error', 'Некорректный ответ при получении категорий');
        }

        $categories = $data['categories'] ?? [];

        SiteCategory::where('site_id', $site->id)->delete();

        foreach ($categories as $category) {
            SiteCategory::create([
                'site_id' => $site->id,
                'joomla_id' => $category['id'] ?? 0,
                'title' => $category['title'] ?? '',
                'alias' => $category['alias'] ?? null,
                'path' => $category['path'] ?? null,
                'parent_id' => $category['parent_id'] ?? null,
                'level' => $category['level'] ?? null,
            ]);
        }

        return redirect()->route('sites.categories', $site);
    }
}
