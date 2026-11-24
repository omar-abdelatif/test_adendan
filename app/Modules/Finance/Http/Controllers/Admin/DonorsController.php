<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donators;
use App\Modules\Finance\Http\Requests\DonorRequest;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Support\Facades\DB;

/** Outside donors (المتبرعين); their donations are in OuterDonationsController. */
class DonorsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المتبرعين');
    }

    public function index()
    {
        return view('pages.donations.donators', ['allDonators' => Donators::latest('id')->get()]);
    }

    public function store(DonorRequest $request)
    {
        Donators::create($request->donorData());

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(DonorRequest $request)
    {
        $donor = Donators::findOrFail($request->integer('id'));
        $data = $request->donorData();

        DB::transaction(function () use ($donor, $data) {
            $donor->update($data);
            // Donations keep a copy of the donor's name for the reports.
            $donor->outerdonations()->update(['name' => $data['name']]);
        });

        return $this->success('تم التحديث بنجاح!');
    }

    /**
     * Donors who gave money stay on record: their donations are in the safe and
     * the reports, for the association or for the sponsorship and new-tombs funds.
     */
    public function destroy(Donators $donor)
    {
        if ($donor->outerdonations()->exists() || $donor->fundDonations()->exists()) {
            return $this->failure('لا يمكن حذف متبرع له تبرعات مسجلة.');
        }
        $donor->delete();

        return $this->success('تم الحذف بنجاح!');
    }
}
