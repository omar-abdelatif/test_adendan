<?php

namespace App\Modules\Cemeteries\Domain\Enums;

/** Who a tomb is for. A mixed tomb takes both, one gender per chamber at a time. */
enum TombServes: string
{
    case Men = 'men';
    case Women = 'women';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'رجال',
            self::Women => 'سيدات',
            self::Mixed => 'مختلطة',
        };
    }

    public function accepts(Gender $gender): bool
    {
        return match ($this) {
            self::Men => $gender === Gender::Male,
            self::Women => $gender === Gender::Female,
            self::Mixed => true,
        };
    }
}
