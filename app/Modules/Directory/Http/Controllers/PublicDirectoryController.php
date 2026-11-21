<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tombs;
use App\Models\Wedding;
use App\Models\Worker;
use App\Modules\Directory\Application\SiteDirectory;
use Illuminate\Http\JsonResponse;

/** Tombs, weddings and craftsmen for the website and the mobile app. */
class PublicDirectoryController extends Controller
{
    public function __construct(private readonly SiteDirectory $directory) {}

    /** Website: the "search" page (dues lookup form + tombs + upcoming weddings). */
    public function searchPage()
    {
        return view('frontend.pages.search', [
            'tombsByRegion' => $this->directory->tombsByRegion(),
            'weddingsByMonth' => $this->directory->weddingsByMonth(),
        ]);
    }

    public function workersPage()
    {
        return view('frontend.pages.workers', $this->directory->workersByCraft());
    }

    public function tombs(): JsonResponse
    {
        return response()->json(Tombs::all());
    }

    public function weddings(): JsonResponse
    {
        return response()->json(Wedding::orderBy('date')->get());
    }

    public function workers(): JsonResponse
    {
        return response()->json(Worker::all());
    }
}
