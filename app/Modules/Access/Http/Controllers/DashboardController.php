<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SpatieActivityLog;

class DashboardController extends Controller
{
    /** Where staff land after logging in. */
    public function home()
    {
        return view('home');
    }

    /** The audit trail of changes made in the dashboard (latest first). */
    public function activity()
    {
        $this->authorize('سجل النشاطات');

        return view('pages.activity.index', [
            'activities' => SpatieActivityLog::with('causer')->latest('id')->limit(2000)->get(),
        ]);
    }
}
