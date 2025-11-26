<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'site_id',
        'joomla_id',
        'title',
        'alias',
        'path',
        'parent_id',
        'level',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
