<?php

namespace App\Modules\Finance\Domain\Enums;

enum OnlineStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}
