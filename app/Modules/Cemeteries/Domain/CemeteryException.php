<?php

namespace App\Modules\Cemeteries\Domain;

use App\Modules\Cemeteries\Domain\Enums\Gender;
use App\Modules\Cemeteries\Domain\Enums\TombServes;
use DomainException;

/** A burial or tomb change the rules refuse; the message is shown to staff as is. */
final class CemeteryException extends DomainException
{
    public static function notEnoughPlaces(string $chamber, int $free, int $asked): self
    {
        return new self($free === 0
            ? "{$chamber} مليانة."
            : "{$chamber} فيها {$free} مكان فاضي بس، والدفنة محتاجة {$asked}.");
    }

    public static function wrongGender(string $tomb, TombServes $serves): self
    {
        return new self("{$tomb} لل{$serves->label()} بس.");
    }

    public static function chamberHoldsOtherGender(string $chamber, Gender $occupants): self
    {
        $who = $occupants === Gender::Male ? 'رجال' : 'سيدات';

        return new self("{$chamber} فيها {$who}، ومينفعش تتخلط لحد ما تتطهر.");
    }

    public static function chamberRequired(string $tomb): self
    {
        return new self("لازم تختار العين أو اللحد في {$tomb}.");
    }

    public static function chamberNotInTomb(): self
    {
        return new self('العين اللي اخترتها مش في المقبرة دي.');
    }

    public static function tombHasBurials(string $tomb, int $count): self
    {
        return new self("{$tomb} فيها {$count} دفنة متسجلة، فمينفعش تتحذف.");
    }

    public static function chamberInUse(string $chamber): self
    {
        return new self("{$chamber} فيها دفنات، فمينفعش تتشال. طهّرها الأول أو سيب العدد زي ما هو.");
    }

    public static function chamberEmpty(string $chamber): self
    {
        return new self("{$chamber} فاضية أصلاً، مفيش حاجة تتطهر.");
    }
}
