<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile app: the logged-in member's payments and dues. */
class MemberFinanceController extends Controller
{
    public function subscriptionPayments(Request $request): JsonResponse
    {
        return $this->ok($request->user()->paymentTransactions()->inFund(Fund::General)
            ->where(fn ($q) => $q
                ->whereIn('transaction_type', TransactionType::subscriptionValues())
                // Older online payments were stored as 'اشتراك' (the SMS spelling) whatever they paid for.
                ->orWhere(fn ($q) => $q
                    ->where('transaction_type', TransactionType::SmsSubscription->value)
                    ->where('item', '!=', 'رسائل')))
            ->latest('id')->get());
    }

    public function donationPayments(Request $request): JsonResponse
    {
        return $this->ok($request->user()->paymentTransactions()->inFund(Fund::General)
            ->whereIn('transaction_type', TransactionType::donationValues())
            ->latest('id')->get());
    }

    public function smsPayments(Request $request): JsonResponse
    {
        return $this->ok($request->user()->paymentTransactions()->inFund(Fund::General)
            ->whereIn('transaction_type', TransactionType::smsValues())
            ->latest('id')->get());
    }

    public function subscriptionDues(Request $request): JsonResponse
    {
        return $this->ok($request->user()->dues()->where('transaction_type', DueType::Subscription->value)->get());
    }

    public function donationDues(Request $request): JsonResponse
    {
        return $this->ok($request->user()->dues()->where('transaction_type', DueType::Donation->value)->get());
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
