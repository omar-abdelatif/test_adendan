<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminders extends Model
{
    protected $fillable = [
        'message',
        'type',
        'sent_at',
        'subscriber_id',
        'mobile_no',
    ];
}
