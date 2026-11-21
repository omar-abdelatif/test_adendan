<?php

namespace App\Modules\Finance\Domain\Enums;

/** The association's two cash accounts. */
enum Account: string
{
    case Safe = 'safe';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'الخزنة',
            self::Bank => 'البنك',
        };
    }
}
