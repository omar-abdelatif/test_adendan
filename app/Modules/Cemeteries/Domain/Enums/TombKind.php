<?php

namespace App\Modules\Cemeteries\Domain\Enums;

/** How a tomb is built: chambers (عيون) that hold several burials, or single graves (لحود). */
enum TombKind: string
{
    case Eyes = 'eyes';
    case Lahd = 'lahd';

    public function label(): string
    {
        return match ($this) {
            self::Eyes => 'عيون',
            self::Lahd => 'لحد',
        };
    }

    /** What one chamber is called on screen. */
    public function unit(): string
    {
        return match ($this) {
            self::Eyes => 'عين',
            self::Lahd => 'لحد',
        };
    }

    /** Places in a new chamber: an عين takes 6, a لحد one burial. */
    public function defaultCapacity(): int
    {
        return match ($this) {
            self::Eyes => 6,
            self::Lahd => 1,
        };
    }
}
