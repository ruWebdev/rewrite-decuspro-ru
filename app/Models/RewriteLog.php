<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewriteLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'article_joomla_id',
        'article_title',
        'status',
        'message',
        'original_content',
        'rewritten_content',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
