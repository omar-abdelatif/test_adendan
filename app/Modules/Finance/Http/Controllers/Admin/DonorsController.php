<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donators;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        Donators::create($this->validated($request));

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        $donor = Donators::findOrFail($request->integer('id'));
        $data = $this->validated($request);

        DB::transaction(function () use ($donor, $data) {
            $donor->update($data);
            // Donations keep a copy of the donor's name for the reports.
            $donor->outerdonations()->update(['name' => $data['name']]);
        });

        return $this->success('تم التحديث بنجاح!');
    }

    /** Donors who gave money stay on record: their donations are in the safe and the reports. */
    public function destroy(Donators $donor)
    {
        if ($donor->outerdonations()->exists()) {
            return $this->failure('لا يمكن حذف متبرع له تبرعات مسجلة.');
        }
        $donor->delete();

        return $this->success('تم الحذف بنجاح!');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'regex:/^0?1[0-9]{9}$/'],
            'donator_type' => ['required', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'الاسم', 'mobile_number' => 'رقم الهاتف', 'donator_type' => 'نوع المتبرع', 'duration' => 'المدة']);
    }
}
