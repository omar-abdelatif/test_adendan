<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

/**
 * Only the manual "send expiry reminders" button is left here; member payments
 * and dues moved to App\Modules\Finance. Moves to the Messaging module next.
 */
class SubscriptionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:الاشتراكات');
    }

    public function runNotifyExpiry()
    {
        Artisan::call('subscriptions:notify-expiry');

        return response()->json([
            'success' => true,
            'message' => trim(Artisan::output()),
        ]);
    }
}
