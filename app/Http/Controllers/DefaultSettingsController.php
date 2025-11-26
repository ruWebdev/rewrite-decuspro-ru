<?php

namespace App\Http\Controllers;

use App\Models\DefaultSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DefaultSettingsController extends Controller
{
    /**
     * Show the default settings form.
     */
    public function edit()
    {
        $settings = DefaultSetting::first();

        return Inertia::render('DefaultSettings', [
            'settings' => $settings ? [
                'skip_external_links' => $settings->skip_external_links,
                'allowed_tags' => $settings->allowed_tags,
                'allowed_attributes' => $settings->allowed_attributes,
            ] : [
                'skip_external_links' => true,
                'allowed_tags' => 'p,h2,h3,h4,h5,img,br,li,ul,ol,i,em,table,tr,td,u,th,thead,tbody',
                'allowed_attributes' => 'src',
            ],
        ]);
    }

    /**
     * Store default settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'skip_external_links' => ['boolean'],
            'allowed_tags' => ['nullable', 'string'],
            'allowed_attributes' => ['nullable', 'string'],
        ]);

        $settings = DefaultSetting::first() ?? new DefaultSetting();

        $settings->fill($validated);
        $settings->save();

        return redirect()->route('default-settings');
    }
}
