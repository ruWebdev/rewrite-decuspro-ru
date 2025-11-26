<?php

use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\DefaultSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RewriteController;
use App\Http\Controllers\SiteController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [SiteController::class, 'index'])
    ->name('rewrite');

Route::post('/sites', [SiteController::class, 'store'])
    ->name('sites.store');

Route::get('/sites/{site}', [SiteController::class, 'show'])
    ->name('sites.show');

Route::delete('/sites/{site}', [SiteController::class, 'destroy'])
    ->name('sites.destroy');

Route::get('/sites/{site}/authors', [SiteController::class, 'authors'])
    ->name('sites.authors');

Route::get('/sites/{site}/categories', [SiteController::class, 'categories'])
    ->name('sites.categories');

Route::get('/sites/{site}/rewrite', [RewriteController::class, 'show'])
    ->name('sites.rewrite');

Route::post('/sites/{site}/rewrite/run', [RewriteController::class, 'run'])
    ->name('sites.rewrite.run');

Route::post('/sites/{site}/rewrite/settings', [RewriteController::class, 'updateSettings'])
    ->name('sites.rewrite.settings');

Route::post('/sites/{site}/rewrite/clear-logs', [RewriteController::class, 'clearLogs'])
    ->name('sites.rewrite.clear-logs');

Route::post('/rewrite-links', [RewriteController::class, 'storeLink'])
    ->name('rewrite-links.store');

Route::delete('/rewrite-links/{link}', [RewriteController::class, 'destroyLink'])
    ->name('rewrite-links.destroy');

Route::post('/rewrite-links/import', [RewriteController::class, 'importLinks'])
    ->name('rewrite-links.import');

Route::post('/sites/{site}/authors/sync', [SiteController::class, 'syncAuthors'])
    ->name('sites.authors.sync');

Route::post('/sites/{site}/categories/sync', [SiteController::class, 'syncCategories'])
    ->name('sites.categories.sync');

Route::get('/default-settings', [DefaultSettingsController::class, 'edit'])
    ->name('default-settings');

Route::post('/default-settings', [DefaultSettingsController::class, 'update'])
    ->name('default-settings.save');

Route::get('/ai-settings', [AiSettingsController::class, 'edit'])
    ->name('ai-settings');

Route::post('/ai-settings', [AiSettingsController::class, 'update'])
    ->name('ai-settings.save');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
