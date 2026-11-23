<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SpatieActivityLog;
use App\Modules\Access\Application\DashboardOverview;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Where staff land after logging in. Money figures are shown only to staff who
     * can open the finance reports; member figures only to staff who manage members.
     */
    public function home(Request $request, DashboardOverview $overview)
    {
        $user = $request->user();
        $seesFinance = $user->can('التقارير');
        $seesMembers = $user->can('المشتركين');

        return view('home', [
            'balances' => $seesFinance ? $overview->balances() : null,
            'collection' => $seesFinance ? $overview->collection() : null,
            'monthly' => $seesFinance ? $overview->monthlyIncome() : null,
            'transactions' => $seesFinance ? $overview->recentTransactions() : null,
            'members' => $seesMembers ? $overview->members() : null,
            'requests' => $seesMembers ? $overview->pendingUpdateRequests() : null,
        ]);
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
