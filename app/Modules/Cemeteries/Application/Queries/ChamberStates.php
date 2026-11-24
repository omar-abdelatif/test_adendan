<?php

namespace App\Modules\Cemeteries\Application\Queries;

use App\Models\Burial;
use App\Models\CemeteryChamber;
use App\Modules\Cemeteries\Domain\ChamberState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** How full each chamber is, from the burials still in it, in one query. */
class ChamberStates
{
    /**
     * @param  Collection<int, CemeteryChamber>  $chambers
     * @return array<int, ChamberState> keyed by chamber id
     */
    public function of(Collection $chambers, ?int $ignoringBurial = null): array
    {
        if ($chambers->isEmpty()) {
            return [];
        }

        $rows = Burial::query()
            ->whereIn('chamber_id', $chambers->pluck('id'))
            ->whereNull('cleared_at')
            ->when($ignoringBurial, fn ($q) => $q->whereKeyNot($ignoringBurial))
            ->selectRaw("chamber_id, SUM(places) AS used, SUM(CASE WHEN gender = 'male' THEN places ELSE 0 END) AS men, SUM(CASE WHEN gender = 'female' THEN places ELSE 0 END) AS women, MAX(burial_date) AS last_burial")
            ->groupBy('chamber_id')
            ->toBase()
            ->get()
            ->keyBy('chamber_id');

        return $chambers->mapWithKeys(function (CemeteryChamber $chamber) use ($rows) {
            $row = $rows->get($chamber->id);

            return [$chamber->id => new ChamberState(
                capacity: $chamber->capacity,
                used: (int) ($row->used ?? 0),
                men: (int) ($row->men ?? 0),
                women: (int) ($row->women ?? 0),
                lastBurial: isset($row->last_burial) ? Carbon::parse($row->last_burial) : null,
            )];
        })->all();
    }

    public function one(CemeteryChamber $chamber, ?int $ignoringBurial = null): ChamberState
    {
        return $this->of(collect([$chamber]), $ignoringBurial)[$chamber->id];
    }
}
