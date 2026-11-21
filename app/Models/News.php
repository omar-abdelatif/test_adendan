<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $category
 * @property string|null $img
 * @property string|null $slug
 */
class News extends Model
{
    use HasFactory;

    protected $table = 'news';

    protected $fillable = [
        'title',
        'description',
        'category',
        'img',
        'shortlink',
        'thumbnail',
        'slug',
        'posted_by',
    ];

    public function thumbnails(): HasMany
    {
        return $this->hasMany(NewsThumbnail::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(NewsVideos::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsComments::class, 'news_id');
    }
}
