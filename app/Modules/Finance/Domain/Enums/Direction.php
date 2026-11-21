<?php

namespace App\Modules\Finance\Domain\Enums;

/** Money in or out of an account (column `transaction_cat`). */
enum Direction: string
{
    case In = 'ايداع';
    case Out = 'سحب';

    /** @return list<string> stored values that mean "out", including legacy ones. */
    public static function outValues(): array
    {
        return [self::Out->value, 'خزينة/سحب'];
    }
}
