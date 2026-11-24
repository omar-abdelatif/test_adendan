<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscribers;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\Fund;

/** One member's payments and outstanding dues. */
class MemberAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:الاشتراكات');
    }

    public function show(Subscribers $subscriber)
    {
        $dues = $subscriber->dues()->get()->groupBy('transaction_type');
        $subDue = $dues->get(DueType::Subscription->value, collect());
        $donDue = $dues->get(DueType::Donation->value, collect());

        return view('pages.subscriptions.subscriptions_details', [
            'subscriber' => $subscriber,
            'subscriptions' => $subscriber->paymentTransactions()->inFund(Fund::General)->latest('id')->get(),
            'subDue' => $subDue,
            'donDue' => $donDue,
            'totalSubscriptionAmount' => $subDue->sum('amount_remaining'),
            'totalDonationAmount' => $donDue->sum('amount_remaining'),
        ]);
    }
}
