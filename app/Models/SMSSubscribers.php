<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SMSSubscribers extends Model
{
    use HasFactory;

    protected $table = 'sms_subscribers';

    protected $fillable = [
        'member_id',
        'mobile_no',
        'amount',
        'subscription_start_date',
        'subscription_expiry_date',
        'active_sms',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscribers::class, 'member_id', 'member_id');
    }
}
