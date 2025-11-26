<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewriteLinkUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'rewrite_link_id',
        'article_joomla_id',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function link()
    {
        return $this->belongsTo(RewriteLink::class, 'rewrite_link_id');
    }
}
