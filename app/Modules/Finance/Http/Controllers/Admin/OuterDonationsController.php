<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donators;
use App\Modules\Finance\Application\Actions\RecordDonation;
use App\Modules\Finance\Http\Requests\OuterDonationRequest;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Carbon\CarbonImmutable;

class OuterDonationsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:التبرعات الخارجية');
    }

    public function index(Donators $donator)
    {
        return view('pages.donations.outer_donations_history', [
            'donators' => $donator,
            'outerdonations' => $donator->outerdonations()->latest('id')->get(),
        ]);
    }

    public function store(OuterDonationRequest $request, RecordDonation $recordDonation)
    {
        $recordDonation->fromDonor(
            donor: Donators::findOrFail($request->integer('id')),
            amount: Money::pounds($request->input('amount')),
            details: $request->only(['invoice_id', 'donation_destination', 'payment_method', 'donator_type']),
            date: CarbonImmutable::now(),
        );

        return $this->success('تم الإضافة بنجاح');
    }
}
