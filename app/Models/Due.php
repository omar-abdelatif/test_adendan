<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Due extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'item',
        'total_amount',
        'amount_paid',
        'amount_remaining',
        'transaction_type',
    ];

    public function subscriber()
    {
        return $this->belongsTo(Subscribers::class, 'member_id', 'member_id');
    }
}
