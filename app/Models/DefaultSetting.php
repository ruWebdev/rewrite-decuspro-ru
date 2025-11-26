<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'skip_external_links',
        'allowed_tags',
        'allowed_attributes',
    ];

    protected $casts = [
        'skip_external_links' => 'boolean',
    ];

    /**
     * Get default values.
     */
    public static function getDefaults(): array
    {
        $settings = self::first();

        return [
            'skip_external_links' => $settings->skip_external_links ?? true,
            'allowed_tags' => $settings->allowed_tags ?? 'p,h2,h3,h4,h5,img,br,li,ul,ol,i,em,table,tr,td,u,th,thead,tbody',
            'allowed_attributes' => $settings->allowed_attributes ?? 'src',
        ];
    }
}
