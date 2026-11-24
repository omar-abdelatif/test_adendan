<?php

namespace App\Modules\Finance\Application\Queries;

use App\Models\CostYears;
use App\Models\Donations;
use App\Models\Due;
use App\Models\OuterDonations;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Treasury;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\PaymentCategory;
use App\Modules\Finance\Domain\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only numbers for the finance report pages. Each method returns exactly
 * the variables its view expects. New rows are matched by `account`; rows
 * written before that column existed are matched by their legacy values.
 */
class FinanceReports
{
    /** Donation categories shown as separate totals on the inner-donations page. */
    private const DONATION_CATEGORIES = [
        'donationDeathCarSum' => 'ص.سيارة',
        'donationTombsSum' => 'مقابر قديمة',
        'donationHeadquartersSum' => 'ص.مقر',
        'donationAffiliateSum' => 'تبرع إنتساب',
        'donationDevSum' => 'تبرع تنمية',
    ];

    public function __construct(private readonly Treasury $treasury) {}

    public function subscriptions(): array
    {
        $currentYear = (string) CostYears::max('year');
        $subscriptionDues = fn () => Due::where('transaction_type', DueType::Subscription->value);

        $subscribers = Subscribers::query()
            ->billable()
            ->with(['paymentTransactions' => fn ($q) => $q
                ->whereIn('transaction_type', TransactionType::subscriptionValues())
                ->whereIn('payment_cat', [PaymentCategory::Partial->value, PaymentCategory::Full->value]),
            ])
            ->get();

        return [
            'subscribers' => $subscribers,
            'count' => $subscribers->count(),
            'currentSubscriptionYear' => $currentYear,
            'sumTotalSubscriptionDues' => $subscriptionDues()->where('item', '!=', $currentYear)->sum('amount_remaining'),
            'subscriptionSumDuePaied' => $subscriptionDues()->sum('amount_paid'),
            'sumTotalCurrentSubscriptionDues' => $subscriptionDues()->where('item', $currentYear)->sum('total_amount'),
            'sumTotalCurrentSubscriptionDuesPaid' => PaymentTransaction::inFund(Fund::General)->whereIn('transaction_type', TransactionType::subscriptionValues())
                ->where('item', $currentYear)
                ->sum('amount'),
        ];
    }

    public function safe(): array
    {
        $safe = fn (): Builder => PaymentTransaction::query()->inFund(Fund::General)->where(fn ($q) => $q
            ->where('account', Account::Safe->value)
            ->orWhereNull('account'));

        return [
            'transactions' => $safe()->latest('id')->get(),
            'sumDonationsAmount' => $safe()->where('transaction_cat', Direction::In->value)
                ->whereIn('transaction_type', TransactionType::donationValues())->sum('amount'),
            'sumSubscriptionsAmount' => $safe()->where('transaction_cat', Direction::In->value)
                ->whereIn('transaction_type', TransactionType::subscriptionValues())->sum('amount'),
            'withdrawalsAmount' => $safe()->whereIn('transaction_cat', Direction::outValues())->sum('amount'),
            // The running balance every withdrawal is checked against.
            'netSafeAmount' => $this->treasury->balance(Account::Safe)->toPounds(),
        ];
    }

    public function bank(): array
    {
        $transactions = PaymentTransaction::query()
            ->inFund(Fund::General)
            ->where(fn ($q) => $q
                ->where('account', Account::Bank->value)
                ->orWhereIn('transaction_type', [TransactionType::LegacyBankDeposit->value, TransactionType::LegacyBankWithdrawal->value]))
            ->latest('id')
            ->get();

        return [
            'transactions' => $transactions,
            'bankAmount' => $this->treasury->balance(Account::Bank)->toPounds(),
        ];
    }

    public function innerDonations(): array
    {
        $totals = Donations::query()
            ->whereIn('donation_category', self::DONATION_CATEGORIES)
            ->groupBy('donation_category')
            ->selectRaw('donation_category, SUM(amount) as total')
            ->pluck('total', 'donation_category');

        $sums = array_map(fn (string $category) => (int) ($totals[$category] ?? 0), self::DONATION_CATEGORIES);

        return $sums + [
            'donations' => Donations::latest('id')->get(),
            'sumTotalDuesDonations' => Due::where('transaction_type', DueType::Donation->value)->sum('amount_remaining'),
        ];
    }

    public function outerDonations(): array
    {
        return ['donators' => OuterDonations::latest('id')->get()];
    }

    /**
     * Old subscription dues, optionally filtered by amount ("=", "<", ">" ...).
     *
     * @return array<string, mixed>
     */
    public function oldDues(?string $amount, ?string $operator): array
    {
        $query = Subscribers::query()
            ->join('dues', 'subscribers.member_id', '=', 'dues.member_id')
            ->where('dues.transaction_type', DueType::Subscription->value)
            ->select('subscribers.name', 'subscribers.member_id', 'dues.total_amount', 'dues.amount_paid', 'dues.amount_remaining', 'dues.item');

        $base = ['noOldDelays' => 'لا توجد مستحقات'];

        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return $base + ['queryResult' => $query->get()];
        }

        $operator = in_array($operator, ['=', '<', '>', '<=', '>=', '!='], true) ? $operator : '=';

        return $base + ['delays' => $query->where('dues.total_amount', $operator, (int) $amount)->get()];
    }
}
