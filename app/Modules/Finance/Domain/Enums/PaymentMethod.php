<?php

namespace App\Modules\Finance\Domain\Enums;

/** How cash was paid at the office (column `payment_method` for office payments). */
enum PaymentMethod: string
{
    case Cash = 'كاش';
}
