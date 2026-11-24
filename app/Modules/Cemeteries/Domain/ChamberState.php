<?php

namespace App\Modules\Cemeteries\Domain;

use App\Modules\Cemeteries\Domain\Enums\Gender;
use Carbon\CarbonInterface;

/** How full one chamber is right now (burials not yet cleared). */
final class ChamberState
{
    public function __construct(
        public readonly int $capacity,
        public readonly int $used,
        public readonly int $men = 0,
        public readonly int $women = 0,
        public readonly ?CarbonInterface $lastBurial = null,
    ) {}

    public function free(): int
    {
        return max(0, $this->capacity - $this->used);
    }

    public function isEmpty(): bool
    {
        return $this->used === 0;
    }

    public function isFull(): bool
    {
        return $this->free() === 0;
    }

    /** The gender already resting here; null when empty (or, in old data, both). */
    public function occupants(): ?Gender
    {
        return match (true) {
            $this->men > 0 && $this->women === 0 => Gender::Male,
            $this->women > 0 && $this->men === 0 => Gender::Female,
            default => null,
        };
    }
}
