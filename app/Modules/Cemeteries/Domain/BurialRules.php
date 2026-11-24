<?php

namespace App\Modules\Cemeteries\Domain;

use App\Modules\Cemeteries\Domain\Enums\Gender;
use App\Modules\Cemeteries\Domain\Enums\TombServes;

/**
 * Whether a burial may go into a chamber:
 * - the tomb must take that gender (رجال بس / سيدات بس / مختلطة);
 * - in a mixed tomb a chamber keeps one gender until it is cleared;
 * - the burial's places must fit in what is left of the chamber.
 */
final class BurialRules
{
    /** @throws CemeteryException */
    public static function check(string $tomb, TombServes $serves, string $chamber, ChamberState $state, Gender $gender, int $places): void
    {
        if (! $serves->accepts($gender)) {
            throw CemeteryException::wrongGender($tomb, $serves);
        }

        $occupants = $state->occupants();
        if ($serves === TombServes::Mixed && $occupants !== null && $occupants !== $gender) {
            throw CemeteryException::chamberHoldsOtherGender($chamber, $occupants);
        }

        if ($places > $state->free()) {
            throw CemeteryException::notEnoughPlaces($chamber, $state->free(), $places);
        }
    }

    /** Whether the chamber could take one more burial of this gender (for the availability screens). */
    public static function accepts(TombServes $serves, ChamberState $state, Gender $gender): bool
    {
        if (! $serves->accepts($gender) || $state->isFull()) {
            return false;
        }
        $occupants = $state->occupants();

        return $serves !== TombServes::Mixed || $occupants === null || $occupants === $gender;
    }
}
