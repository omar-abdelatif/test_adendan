<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SMSFEES extends Model
{
    use HasFactory;

    protected $table = 'sms_fees';

    protected $fillable = ['item', 'amount'];
}
