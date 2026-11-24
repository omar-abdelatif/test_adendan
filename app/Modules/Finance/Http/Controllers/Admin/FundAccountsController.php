<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Modules\Finance\Application\Actions\TransferFunds;
use App\Modules\Finance\Application\Queries\FundViews;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Http\Middleware\FundAccess;
use App\Modules\Finance\Http\Requests\TransferRequest;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use App\Modules\Shared\Infrastructure\Excel\SheetExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** A fund's safe and bank: both balances, one month of movements, and transfers between them. */
class FundAccountsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware(FundAccess::class.':accounts');
    }

    public function show(Request $request, Fund $fund, FundViews $views)
    {
        return view('pages.funds.accounts', $views->accounts($fund, FundViews::month($request->query('month'))) + ['fund' => $fund]);
    }

    public function export(Request $request, Fund $fund, FundViews $views)
    {
        $data = $views->accounts($fund, FundViews::month($request->query('month')));
        $rows = $data['transactions']->map(fn (PaymentTransaction $t) => [
            $t->payment_date, $t->item, Account::tryFrom((string) $t->account)?->label(), $t->transaction_cat, $t->inv_no ?: null, $t->amount,
        ])->values()->all();

        return Excel::download(
            new SheetExport("{$fund->label()} {$data['month']->format('Y-m')}", ['التاريخ', 'البند', 'الحساب', 'الاتجاه', 'رقم الإيصال', 'المبلغ'], $rows),
            "accounts-{$fund->value}-{$data['month']->format('Y-m')}.xlsx",
        );
    }

    /** Safe to bank or bank to safe, inside the fund, with a photo of the deposit slip. */
    public function transfer(TransferRequest $request, Fund $fund, TransferFunds $transfer)
    {
        $request->validate(['from' => ['required', 'in:safe,bank']]);
        $from = Account::from($request->input('from'));
        $to = $from === Account::Safe ? Account::Bank : Account::Safe;

        return $this->attempt(
            fn () => $transfer->handle($from, $to, Money::pounds($request->input('amount')), $request->file('proof_img'), $request->transferDate(), $fund),
            'اتحول '.number_format((int) $request->input('amount'))." ج.م من {$from->label()} لـ{$to->label()}",
        );
    }
}
