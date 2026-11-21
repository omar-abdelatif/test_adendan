<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Application\Actions\TransferFunds;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Exceptions\InsufficientFunds;
use App\Modules\Finance\Http\Requests\TransferRequest;
use App\Modules\Shared\Domain\Money;

/** Moving cash between the safe and the bank account. */
class TransfersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:التقارير');
    }

    public function safeToBank(TransferRequest $request, TransferFunds $transfer)
    {
        return $this->transfer($request, $transfer, Account::Safe, Account::Bank);
    }

    public function bankToSafe(TransferRequest $request, TransferFunds $transfer)
    {
        return $this->transfer($request, $transfer, Account::Bank, Account::Safe);
    }

    private function transfer(TransferRequest $request, TransferFunds $transfer, Account $from, Account $to)
    {
        try {
            $transfer->handle($from, $to, Money::pounds($request->input('amount')), $request->file('proof_img'), $request->transferDate());
        } catch (InsufficientFunds) {
            return back()->withErrors(['amount' => 'المبلغ الذي أدخلته غير متوفر. الرجاء إدخال مبلغ أقل والمحاولة مرة أخرى.']);
        }

        return back()->with(['message' => 'تم السحب', 'alert-type' => 'success']);
    }
}
