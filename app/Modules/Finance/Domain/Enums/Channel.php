<?php

namespace App\Modules\Finance\Domain\Enums;

/** Where the money was handled (column `transaction_method`). */
enum Channel: string
{
    case Office = 'المقر';
    case Online = 'دفع اونلاين';
    case Transfer = 'تحويلات';
}
