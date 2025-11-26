<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteAuthor extends Model
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
        'name',
        'username',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
