<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'url',
        'skip_external_links',
        'allowed_tags',
        'allowed_attributes',
    ];

    protected $casts = [
        'skip_external_links' => 'boolean',
    ];

    /**
     * Boot the model and set default values from DefaultSetting.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($site) {
            $defaults = DefaultSetting::getDefaults();

            if (!isset($site->skip_external_links)) {
                $site->skip_external_links = $defaults['skip_external_links'];
            }
            if (!isset($site->allowed_tags)) {
                $site->allowed_tags = $defaults['allowed_tags'];
            }
            if (!isset($site->allowed_attributes)) {
                $site->allowed_attributes = $defaults['allowed_attributes'];
            }
        });
    }
}
