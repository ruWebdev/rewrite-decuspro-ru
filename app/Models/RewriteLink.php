<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewriteLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'domain',
        'anchor',
    ];

    public function usages()
    {
        return $this->hasMany(RewriteLinkUsage::class);
    }

    /**
     * Extract domain from URL and set it automatically.
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->url && !$model->domain) {
                $model->domain = parse_url($model->url, PHP_URL_HOST) ?: '';
            }
        });
    }
}
