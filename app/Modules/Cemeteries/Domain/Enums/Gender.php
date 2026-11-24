<?php

namespace App\Modules\Cemeteries\Domain\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'ذكر',
            self::Female => 'أنثى',
        };
    }

    /** "for a man" / "for a woman", as the availability screens say it. */
    public function forWhom(): string
    {
        return match ($this) {
            self::Male => 'لراجل',
            self::Female => 'لسيدة',
        };
    }
}
