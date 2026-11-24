<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donators;
use App\Models\FundDonation;
use App\Modules\Finance\Application\Queries\FundViews;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Http\Middleware\FundAccess;
use App\Modules\Finance\Http\Requests\DonorRequest;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;

/** The donors of the sponsorship or new-tombs fund, and one donor's history (the drawer). */
class FundDonorsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware(FundAccess::class.':donations');
    }

    public function index(Fund $fund, FundViews $views)
    {
        return view('pages.funds.donors', $views->donors($fund) + [
            'fund' => $fund,
            'categories' => $fund->donationCategories(),
            'months' => FundDonation::MONTHS,
        ]);
    }

    /** The drawer content: the donor and their donations to this fund. */
    public function show(Fund $fund, Donators $donor, FundViews $views)
    {
        return view('pages.funds.partials.donor-history', [
            'fund' => $fund,
            'donor' => $donor,
            'donations' => $views->history($donor, $fund),
            'elsewhere' => $donor->fundDonations()->where('fund', '!=', $fund->value)->count() + $donor->outerdonations()->count(),
        ]);
    }

    public function store(Fund $fund, DonorRequest $request)
    {
        $donor = Donators::create($request->donorData());

        return back()->with(['message' => "اتضاف المتبرع {$donor->name}", 'alert-type' => 'success', 'give_to' => $donor->id]);
    }

    public function update(Fund $fund, DonorRequest $request, Donators $donor)
    {
        $donor->update($request->donorData());
        $donor->outerdonations()->update(['name' => $donor->name]);

        return $this->success("اتعدلت بيانات {$donor->name}");
    }
}
