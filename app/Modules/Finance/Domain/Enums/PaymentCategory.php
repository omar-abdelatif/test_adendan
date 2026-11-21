<?php

namespace App\Modules\Finance\Domain\Enums;

/** Whether a payment settled the whole amount due (column `payment_cat`). */
enum PaymentCategory: string
{
    case Full = 'كلي';
    case Partial = 'جزئي';
}
