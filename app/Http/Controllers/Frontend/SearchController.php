<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Tombs;
use App\Models\Wedding;
use Carbon\Carbon;

class SearchController extends Controller
{
    public $count;

    public function index()
    {
        $weddingsByMonth = $this->getWeddingsByMonth();
        $tombsByRegion = $this->getTombsByRegion();

        return view('frontend.pages.search', compact('tombsByRegion', 'weddingsByMonth'));
    }

    public function getTombsByRegion()
    {
        $distinctRegions = Tombs::distinct()->pluck('region');
        $tombsByRegion = [];
        foreach ($distinctRegions as $region) {
            $tombs = Tombs::where('region', $region)->get();
            $tombsByRegion[$region] = $tombs;
        }

        return $tombsByRegion;
    }

    public function getWeddingsByMonth()
    {
        $months = include base_path('lang/ar/months.php');
        $weddings = Wedding::whereDate('date', '>=', now())->get();
        $weddingsByMonth = $weddings->groupBy(function ($wedding) use ($months) {
            $englishMonth = Carbon::parse($wedding->date)->format('F');
            $arabicMonth = $months[$englishMonth]['name'];
            $year = Carbon::parse($wedding->date)->format('Y');

            return $arabicMonth.' '.$year;
        });
        $sortedMonths = $weddingsByMonth->sortBy(function ($weds, $monthName) {
            $firstDate = $weds->first()->date;

            return Carbon::parse($firstDate)->timestamp;
        });

        return $sortedMonths;
    }
}
