<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsComments extends Model {
    protected $fillable = [
        'author',
        'content',
        'news_id',
        'subscriber_id',
    ];
    public function isOwnedBy($subscriber): bool {
        return $subscriber && $this->subscriber_id !== null && (int) $this->subscriber_id === (int) $subscriber->id;
    }
    public function news() {
        return $this->belongsTo(News::class, 'news_id');
    }
}
