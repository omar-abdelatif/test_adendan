<?php

namespace App\Modules\Members\Domain;

/** subscribers.status, as the dashboard shows it. */
enum MemberStatus: int
{
    case Inactive = 0;
    case Active = 1;
    case Deceased = 2;
    case Suspended = 3;

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'الإشتراك غير مفعل',
            self::Active => 'الإشتراك مفعل',
            self::Deceased => 'المشترك متوفي',
            self::Suspended => 'الإشتراك معلق',
        };
    }
}
