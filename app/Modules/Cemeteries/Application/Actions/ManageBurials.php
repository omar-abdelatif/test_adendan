<?php

namespace App\Modules\Cemeteries\Application\Actions;

use App\Models\Burial;
use App\Models\CemeteryChamber;
use App\Models\CemeteryRegion;
use App\Models\CemeteryTomb;
use App\Modules\Cemeteries\Application\Queries\ChamberStates;
use App\Modules\Cemeteries\Domain\BurialRules;
use App\Modules\Cemeteries\Domain\CemeteryException;
use App\Modules\Cemeteries\Domain\Enums\Gender;
use App\Modules\Cemeteries\Domain\Enums\TombKind;
use App\Modules\Cemeteries\Infrastructure\BurialFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Records burials, moves or edits them, and clears chambers.
 *
 * The chamber row is locked while a burial is placed, so two people registering
 * burials into the same chamber at the same moment cannot overfill it.
 */
class ManageBurials
{
    private const DETAILS = ['subscriber_id', 'name', 'gender', 'age', 'death_place', 'death_date', 'burial_date', 'burial_cost', 'washer', 'carrier', 'notes'];

    public function __construct(private readonly ChamberStates $states, private readonly BurialFiles $files) {}

    /**
     * @param  array<string, mixed>  $data  validated form data (region_id, tomb_id, chamber_id, places + details)
     * @param  array<string, UploadedFile|null>  $uploads  kind => file
     *
     * @throws CemeteryException
     */
    public function record(array $data, array $uploads = []): Burial
    {
        $burial = DB::transaction(fn () => Burial::create([...Arr::only($data, self::DETAILS), ...$this->place($data, null)]));
        $this->attach($burial, $uploads);

        return $burial;
    }

    /**
     * A burial from before the system, or one moved out of a chamber long ago:
     * kept in the records, taking no place.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPast(array $data): Burial
    {
        $region = CemeteryRegion::findOrFail($data['region_id']);
        $tomb = empty($data['tomb_id']) ? null : $region->tombs()->findOrFail($data['tomb_id']);

        return Burial::create([
            ...Arr::only($data, self::DETAILS),
            'region_id' => $region->id,
            'tomb_id' => $tomb?->id,
            'chamber_id' => null,
            'places' => 1,
            'cleared_at' => now(),
            'legacy_source' => $data['legacy_source'] ?? null,
            'legacy_id' => $data['legacy_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $uploads
     *
     * @throws CemeteryException
     */
    public function update(Burial $burial, array $data, array $uploads = []): void
    {
        DB::transaction(function () use ($burial, $data) {
            // A past burial stays out of the chambers; only its details change.
            $placement = $burial->cleared_at ? [] : $this->place($data, $burial->id);
            $burial->update([...Arr::only($data, self::DETAILS), ...$placement]);
        });
        $this->attach($burial, $uploads, replace: true);
    }

    public function delete(Burial $burial): void
    {
        DB::transaction(function () use ($burial) {
            foreach ($burial->files as $file) {
                $this->files->delete($file);
            }
            $burial->delete();
        });
    }

    /**
     * Clears a chamber: its burials stay in the records, marked cleared, and the
     * chamber is empty again.
     *
     * @return int how many burials were cleared
     *
     * @throws CemeteryException when the chamber is already empty
     */
    public function clearChamber(CemeteryChamber $chamber): int
    {
        return DB::transaction(function () use ($chamber) {
            $locked = CemeteryChamber::query()->with('tomb')->whereKey($chamber->id)->lockForUpdate()->firstOrFail();
            $names = $locked->activeBurials()->pluck('name');
            if ($names->isEmpty()) {
                throw CemeteryException::chamberEmpty($locked->label());
            }

            $locked->activeBurials()->update(['cleared_at' => now()]);
            $locked->update(['last_cleared_at' => now()]);

            activity('Cemeteries')->performedOn($locked->tomb)
                ->withProperties(['attributes' => ['العين' => $locked->label(), 'الدفنات' => $names->implode('، ')]])
                ->event('updated')
                ->log("تم تطهير {$locked->label()} في {$locked->tomb->name} ({$names->count()} دفنة).");

            return $names->count();
        });
    }

    /**
     * Where a new or moved burial goes, after checking the tomb's rules.
     *
     * @param  array<string, mixed>  $data
     * @return array{region_id: int, tomb_id: int|null, chamber_id: int|null, places: int}
     *
     * @throws CemeteryException
     */
    private function place(array $data, ?int $burialId): array
    {
        $region = CemeteryRegion::findOrFail($data['region_id']);
        $tomb = empty($data['tomb_id']) ? null : CemeteryTomb::query()->where('region_id', $region->id)->findOrFail($data['tomb_id']);

        if ($region->is_village) {
            return ['region_id' => $region->id, 'tomb_id' => $tomb?->id, 'chamber_id' => null, 'places' => 1];
        }
        if ($tomb === null) {
            throw new CemeteryException('اختار المقبرة.');
        }
        if (empty($data['chamber_id'])) {
            throw CemeteryException::chamberRequired($tomb->name);
        }

        $chamber = CemeteryChamber::query()->whereKey($data['chamber_id'])->lockForUpdate()->first();
        if ($chamber === null || $chamber->tomb_id !== $tomb->id) {
            throw CemeteryException::chamberNotInTomb();
        }
        $chamber->setRelation('tomb', $tomb);

        $places = $tomb->kind === TombKind::Lahd ? 1 : max(1, (int) ($data['places'] ?? 1));
        BurialRules::check($tomb->name, $tomb->serves, $chamber->label(), $this->states->one($chamber, $burialId), Gender::from((string) $data['gender']), $places);

        return ['region_id' => $region->id, 'tomb_id' => $tomb->id, 'chamber_id' => $chamber->id, 'places' => $places];
    }

    /** @param array<string, UploadedFile|null> $uploads */
    private function attach(Burial $burial, array $uploads, bool $replace = false): void
    {
        foreach (array_filter($uploads) as $kind => $file) {
            if ($replace) {
                foreach ($burial->files()->where('kind', $kind)->get() as $old) {
                    $this->files->delete($old);
                }
            }
            $this->files->store($burial, $file, $kind);
        }
    }
}
