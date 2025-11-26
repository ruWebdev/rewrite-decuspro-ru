<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    /**
     * Show the AI settings form.
     */
    public function edit()
    {
        $settings = AiSetting::query()->first();

        return inertia('AiSettings', [
            'settings' => $settings ? [
                'deepseek_api' => $settings->deepseek_api,
                'prompt' => $settings->prompt,
                'domain_usage_limit' => $settings->domain_usage_limit ?? 1,
            ] : [
                'deepseek_api' => '',
                'prompt' => '',
                'domain_usage_limit' => 1,
            ],
        ]);
    }

    /**
     * Store AI settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'deepseek_api' => ['nullable', 'string', 'max:255'],
            'prompt' => ['nullable', 'string'],
            'domain_usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $settings = AiSetting::query()->first() ?? new AiSetting();

        $settings->fill($validated);
        $settings->save();

        return redirect()->route('ai-settings');
    }
}
