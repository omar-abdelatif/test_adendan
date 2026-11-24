<?php

namespace App\Modules\Directory\Application;

use App\Models\Tombs;
use App\Models\Wedding;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** The directory pages of the public website. */
class SiteDirectory
{
    /** Craft values as the dashboard form stores them, keyed by the view's variable name. */
    private const CRAFTS = [
        'carpenter' => 'نجار',
        'painter' => 'نقاش',
        'electric' => 'كهربائي',
        'plumber' => 'سباك',
        'tech' => 'فني',
        'other' => 'أخرى',
    ];

    /** Tombs grouped by region name. */
    public function tombsByRegion(): Collection
    {
        return Tombs::with('regionRecord')->orderBy('name')->get()->sortBy('region')->groupBy('region');
    }

    /** Upcoming weddings grouped by "<Arabic month> <year>", in date order. */
    public function weddingsByMonth(): Collection
    {
        $months = include base_path('lang/ar/months.php');

        return Wedding::whereDate('date', '>=', today())->orderBy('date')->get()
            ->groupBy(function (Wedding $wedding) use ($months) {
                $date = Carbon::parse($wedding->date);

                return ($months[$date->format('F')]['name'] ?? $date->format('F')).' '.$date->year;
            });
    }

    /** Workers page: 12 per craft, each craft with its own page number. */
    public function workersByCraft(): array
    {
        $data = [];
        foreach (self::CRAFTS as $key => $craft) {
            $page = Worker::where('craft', $craft)->latest()->paginate(12, ['*'], $key.'_page');
            $data[$key] = $page;
            $data[$key.'Count'] = $page->total();
        }

        return $data;
    }
}
