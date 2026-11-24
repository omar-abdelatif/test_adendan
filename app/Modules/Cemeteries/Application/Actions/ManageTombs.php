<?php

namespace App\Modules\Cemeteries\Application\Actions;

use App\Models\CemeteryChamber;
use App\Models\CemeteryTomb;
use App\Modules\Cemeteries\Domain\CemeteryException;
use App\Modules\Cemeteries\Domain\Enums\TombKind;
use Illuminate\Support\Facades\DB;

/**
 * Adds, changes and removes tombs. A tomb's chambers are numbered 1..n and made
 * here, so a tomb never ends up with a gap or a chamber of the wrong size.
 */
class ManageTombs
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, int $chambers): CemeteryTomb
    {
        return DB::transaction(function () use ($attributes, $chambers) {
            $tomb = CemeteryTomb::create($attributes);
            $this->addChambers($tomb, 1, $chambers);

            return $tomb;
        });
    }

    /**
     * Changes the tomb's details. The chamber count can grow, and shrink only by
     * chambers that never had a burial. The kind can change only while the tomb is empty.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws CemeteryException
     */
    public function update(CemeteryTomb $tomb, array $attributes, int $chambers): void
    {
        DB::transaction(function () use ($tomb, $attributes, $chambers) {
            $kind = TombKind::from((string) ($attributes['kind'] ?? $tomb->kind->value));
            if ($kind !== $tomb->kind && $tomb->burials()->exists()) {
                throw new CemeteryException("{$tomb->name} فيها دفنات، فمينفعش يتغير نوعها.");
            }

            $tomb->update($attributes);
            if ($tomb->wasChanged('kind')) {
                $tomb->chambers()->update(['capacity' => $kind->defaultCapacity()]);
            }

            $current = (int) $tomb->chambers()->max('number');
            if ($chambers > $current) {
                $this->addChambers($tomb, $current + 1, $chambers);
            }
            foreach ($tomb->chambers()->where('number', '>', $chambers)->withCount('burials')->get() as $extra) {
                if ($extra->burials_count > 0) {
                    throw CemeteryException::chamberInUse($kind->unit().' '.$extra->number);
                }
                $extra->delete();
            }
        });
    }

    /** @throws CemeteryException when the tomb has burials (in a chamber or in its records) */
    public function delete(CemeteryTomb $tomb): void
    {
        $count = $tomb->burials()->count();
        if ($count > 0) {
            throw CemeteryException::tombHasBurials($tomb->name, $count);
        }
        $tomb->delete();
    }

    private function addChambers(CemeteryTomb $tomb, int $from, int $to): void
    {
        for ($number = $from; $number <= $to; $number++) {
            CemeteryChamber::create(['tomb_id' => $tomb->id, 'number' => $number, 'capacity' => $tomb->kind->defaultCapacity()]);
        }
    }
}
