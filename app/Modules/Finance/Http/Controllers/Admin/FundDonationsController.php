<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donators;
use App\Models\FundDonation;
use App\Modules\Finance\Application\Actions\RecordFundDonation;
use App\Modules\Finance\Application\Queries\FundViews;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Http\Middleware\FundAccess;
use App\Modules\Finance\Http\Requests\FundDonationRequest;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use App\Modules\Shared\Infrastructure\Excel\SheetExport;
use DomainException;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** Recording, correcting and removing donations to a fund; the receipt; the monthly report. */
class FundDonationsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware(FundAccess::class.':donations');
    }

    /** The donations of one month, with totals per category. */
    public function report(Request $request, Fund $fund, FundViews $views)
    {
        $month = FundViews::month($request->query('month'));

        return view('pages.funds.donations', $views->monthDonations($fund, $month) + ['fund' => $fund, 'month' => $month]);
    }

    public function export(Request $request, Fund $fund, FundViews $views)
    {
        $month = FundViews::month($request->query('month'));
        $report = $views->monthDonations($fund, $month);
        $rows = $report['donations']->map(fn (FundDonation $d) => [
            $d->donated_on->format('Y-m-d'), $d->donor?->name, $d->invoice_no, $d->categoryLabel() ?? $fund->label(), $d->monthsLabel(), $d->account->label(), $d->amount,
        ])->values()->all();

        return Excel::download(
            new SheetExport("تبرعات {$month->format('Y-m')}", ['التاريخ', 'المتبرع', 'رقم الإيصال', 'البند', 'الشهور', 'اتدفع في', 'المبلغ'], $rows),
            "donations-{$fund->value}-{$month->format('Y-m')}.xlsx",
        );
    }

    public function store(FundDonationRequest $request, Fund $fund, RecordFundDonation $donations)
    {
        $donor = Donators::findOrFail($request->integer('donator_id'));
        try {
            $donation = $donations->record($donor, $fund, $request->donationData());
        } catch (DomainException $e) {
            return $this->failure($e->getMessage());
        }

        return back()->with([
            'message' => 'اتسجل تبرع '.number_format($donation->amount)." ج.م من {$donor->name}",
            'alert-type' => 'success',
            'receipt' => route('funds.donations.receipt', [$fund, $donation]),
        ]);
    }

    public function update(FundDonationRequest $request, Fund $fund, FundDonation $donation, RecordFundDonation $donations)
    {
        abort_unless($donation->fund === $fund, 404);

        return $this->attempt(fn () => $donations->update($donation, $request->donationData()), 'اتعدل التبرع');
    }

    public function destroy(Fund $fund, FundDonation $donation, RecordFundDonation $donations)
    {
        abort_unless($donation->fund === $fund, 404);

        return $this->attempt(fn () => $donations->delete($donation), 'اتلغى التبرع، واتسجل قيد بيرجّع المبلغ');
    }

    /** A receipt page ready to print. */
    public function receipt(Fund $fund, FundDonation $donation)
    {
        abort_unless($donation->fund === $fund, 404);

        return view('pages.funds.receipt', ['fund' => $fund, 'donation' => $donation->load('donor')]);
    }
}
