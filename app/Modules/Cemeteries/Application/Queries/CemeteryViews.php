<?php

namespace App\Modules\Cemeteries\Application\Queries;

use App\Models\Burial;
use App\Models\CemeteryChamber;
use App\Models\CemeteryRegion;
use App\Models\CemeteryTomb;
use App\Modules\Cemeteries\Domain\ChamberState;
use App\Modules\Cemeteries\Domain\Enums\Gender;
use App\Modules\Cemeteries\Domain\Enums\TombServes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** What the cemeteries screens show: the overview, one tomb, and the burial form's choices. */
class CemeteryViews
{
    public function __construct(private readonly ChamberStates $states) {}

    /**
     * Every region with the places free for a man and for a woman, the village log,
     * the totals, and the latest burials.
     *
     * @return array{regions: list<array<string, mixed>>, village: array{region: CemeteryRegion, count: int, last: Carbon|null}|null, totals: array<string, int>, recent: Collection<int, Burial>}
     */
    public function overview(): array
    {
        $regions = CemeteryRegion::with(['tombs' => fn ($q) => $q->orderBy('name'), 'tombs.chambers'])->orderBy('sort')->orderBy('name')->get();
        $states = $this->states->of($regions->flatMap(fn ($r) => $r->tombs)->flatMap(fn ($t) => $t->chambers));

        $cards = [];
        $totals = ['tombs' => 0, 'freeMen' => 0, 'totalMen' => 0, 'freeWomen' => 0, 'totalWomen' => 0];
        $village = null;

        foreach ($regions as $region) {
            if ($region->is_village) {
                $log = Burial::query()->inVillage()->where('region_id', $region->id);
                $village = ['region' => $region, 'count' => (clone $log)->count(), 'last' => $this->date((clone $log)->max('burial_date'))];

                continue;
            }

            $places = $this->places($region->tombs, $states);
            $last = collect($region->tombs)->flatMap(fn ($t) => $t->chambers)->map(fn ($c) => $states[$c->id]->lastBurial)->filter()->max();
            $cards[] = [
                'region' => $region,
                'tombs' => $region->tombs,
                'kinds' => $region->tombs->map(fn ($t) => $t->kind->label())->unique()->implode(' و'),
                'men' => $places[Gender::Male->value],
                'women' => $places[Gender::Female->value],
                'last' => $last,
                'nearlyFull' => $this->nearlyFull($places),
                // Each tomb's own free places, for its row in the card.
                'tombPlaces' => $region->tombs->mapWithKeys(fn (CemeteryTomb $t) => [$t->id => [
                    'free' => $t->chambers->sum(fn ($c) => $states[$c->id]->free()),
                    'total' => $t->chambers->sum(fn ($c) => $states[$c->id]->capacity),
                    'chambers' => $t->chambers->count(),
                ]])->all(),
            ];

            $totals['tombs'] += $region->tombs->count();
            $totals['freeMen'] += $places['male']['free'];
            $totals['totalMen'] += $places['male']['total'];
            $totals['freeWomen'] += $places['female']['free'];
            $totals['totalWomen'] += $places['female']['total'];
        }

        $totals['regions'] = count($cards);
        $totals['thisYear'] = Burial::query()->whereYear('burial_date', now()->year)->count();

        return [
            'regions' => $cards,
            'village' => $village,
            'totals' => $totals,
            'recent' => Burial::query()->with(['region', 'tomb', 'chamber.tomb'])->whereNotNull('burial_date')->latest('burial_date')->latest('id')->limit(5)->get(),
        ];
    }

    /**
     * One tomb with each chamber's state and the burials still in it.
     *
     * @return array{tomb: CemeteryTomb, chambers: list<array{chamber: CemeteryChamber, state: ChamberState, burials: Collection<int, Burial>}>, used: int, capacity: int, last: Carbon|null}
     */
    public function tomb(CemeteryTomb $tomb): array
    {
        $tomb->load(['region', 'chambers']);
        $states = $this->states->of($tomb->chambers);
        $burials = Burial::query()->with(['member:id,member_id', 'files'])
            ->whereIn('chamber_id', $tomb->chambers->pluck('id'))->whereNull('cleared_at')
            ->orderByDesc('burial_date')->get()->groupBy('chamber_id');

        $chambers = $tomb->chambers->map(function (CemeteryChamber $chamber) use ($tomb, $states, $burials) {
            $chamber->setRelation('tomb', $tomb);

            return ['chamber' => $chamber, 'state' => $states[$chamber->id], 'burials' => $burials->get($chamber->id, collect())];
        })->all();

        return [
            'tomb' => $tomb,
            'chambers' => $chambers,
            'used' => array_sum(array_map(fn ($s) => $s->used, $states)),
            'capacity' => array_sum(array_map(fn ($s) => $s->capacity, $states)),
            'last' => collect($states)->map(fn ($s) => $s->lastBurial)->filter()->max(),
        ];
    }

    /**
     * Regions → tombs → chambers with what is free, for the burial form to filter
     * as the user picks the gender.
     *
     * @return list<array<string, mixed>>
     */
    public function burialChoices(): array
    {
        $regions = CemeteryRegion::with(['tombs' => fn ($q) => $q->orderBy('name'), 'tombs.chambers'])->orderBy('sort')->orderBy('name')->get();
        $states = $this->states->of($regions->flatMap(fn ($r) => $r->tombs)->flatMap(fn ($t) => $t->chambers));

        return $regions->map(fn (CemeteryRegion $region) => [
            'id' => $region->id,
            'name' => $region->name,
            'village' => $region->is_village,
            'tombs' => $region->tombs->map(fn (CemeteryTomb $tomb) => [
                'id' => $tomb->id,
                'name' => $tomb->name,
                'kind' => $tomb->kind->value,
                'serves' => $tomb->serves->value,
                'chambers' => $tomb->chambers->map(fn (CemeteryChamber $c) => [
                    'id' => $c->id,
                    'label' => $tomb->kind->unit().' '.$c->number,
                    'free' => $states[$c->id]->free(),
                    'occupants' => $states[$c->id]->occupants()?->value,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, CemeteryTomb>  $tombs
     * @param  array<int, ChamberState>  $states
     * @return array{male: array{free: int, total: int}, female: array{free: int, total: int}}
     */
    private function places(Collection $tombs, array $states): array
    {
        $places = ['male' => ['free' => 0, 'total' => 0], 'female' => ['free' => 0, 'total' => 0]];
        foreach ($tombs as $tomb) {
            foreach ($tomb->chambers as $chamber) {
                $state = $states[$chamber->id];
                foreach (Gender::cases() as $gender) {
                    if (! $tomb->serves->accepts($gender)) {
                        continue;
                    }
                    // A mixed chamber already holding the other gender is not counted for this one.
                    $taken = $state->occupants();
                    if ($tomb->serves === TombServes::Mixed && $taken !== null && $taken !== $gender) {
                        continue;
                    }
                    $places[$gender->value]['total'] += $state->capacity;
                    $places[$gender->value]['free'] += $state->free();
                }
            }
        }

        return $places;
    }

    /** @param array{male: array{free: int, total: int}, female: array{free: int, total: int}} $places */
    private function nearlyFull(array $places): bool
    {
        foreach ($places as $p) {
            if ($p['total'] > 0 && $p['free'] / $p['total'] <= 0.2) {
                return true;
            }
        }

        return false;
    }

    private function date(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
