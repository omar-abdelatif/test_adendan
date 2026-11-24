<?php

namespace App\Modules\Finance\Application\Queries;

use App\Models\Donators;
use App\Models\FundDonation;
use App\Models\PaymentTransaction;
use App\Modules\Finance\Application\Treasury;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** What the fund pages show: the donors of a fund, and its safe and bank. */
class FundViews
{
    public function __construct(private readonly Treasury $treasury) {}

    /** "2026-11" from a month filter; anything else means this month. */
    public static function month(?string $value): CarbonImmutable
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $value);
            if ($month !== null && $month->year >= 2000 && $month->lessThanOrEqualTo(CarbonImmutable::now()->endOfMonth())) {
                return $month->startOfMonth();
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * Every donor (the donors table is shared by all funds), with what they gave to this fund.
     *
     * @return array{donors: Collection<int, Donators>, totals: array{donors: int, givers: int, month: int, year: int}}
     */
    public function donors(Fund $fund): array
    {
        $inFund = fn ($q) => $q->where('fund', $fund->value);
        $donors = Donators::query()
            ->withCount(['fundDonations as fund_count' => $inFund])
            ->withSum(['fundDonations as fund_total' => $inFund], 'amount')
            ->withMax(['fundDonations as fund_last' => $inFund], 'donated_on')
            ->orderByDesc('fund_last')->orderBy('name')
            ->get();

        $now = CarbonImmutable::now();
        $sum = fn (CarbonImmutable $from) => (int) FundDonation::query()->inFund($fund)->where('donated_on', '>=', $from->toDateString())->sum('amount');

        return [
            'donors' => $donors,
            'totals' => [
                'donors' => $donors->count(),
                'givers' => $donors->where('fund_count', '>', 0)->count(),
                'month' => $sum($now->startOfMonth()),
                'year' => $sum($now->startOfYear()),
            ],
        ];
    }

    /** @return Collection<int, FundDonation> one donor's donations to this fund, newest first */
    public function history(Donators $donor, Fund $fund): Collection
    {
        return $donor->fundDonations()->inFund($fund)->latest('donated_on')->latest('id')->get();
    }

    /**
     * The fund's two balances and the movements of one month.
     *
     * @return array{safe: int, bank: int, month: CarbonImmutable, transactions: Collection<int, PaymentTransaction>, in: int, out: int}
     */
    public function accounts(Fund $fund, CarbonImmutable $month): array
    {
        $transactions = PaymentTransaction::query()->inFund($fund)
            ->whereBetween('payment_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->latest('payment_date')->latest('id')
            ->get();
        // Transfers move money between the fund's own accounts: neither income nor spending.
        $real = $transactions->where('transaction_type', '!=', TransactionType::Transfer->value);

        return [
            'safe' => $this->treasury->balance(Account::Safe, $fund)->toPounds(),
            'bank' => $this->treasury->balance(Account::Bank, $fund)->toPounds(),
            'month' => $month,
            'transactions' => $transactions,
            'in' => (int) $real->where('transaction_cat', Direction::In->value)->sum('amount'),
            'out' => (int) $real->where('transaction_cat', Direction::Out->value)->sum('amount'),
        ];
    }

    /**
     * The donations of one month, with totals per category (the monthly donations report).
     *
     * @return array{donations: Collection<int, FundDonation>, byCategory: array<string, int>, total: int}
     */
    public function monthDonations(Fund $fund, CarbonImmutable $month): array
    {
        $donations = FundDonation::query()->inFund($fund)->with('donor')
            ->whereBetween('donated_on', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->latest('donated_on')->latest('id')
            ->get();

        $byCategory = $donations->groupBy(fn (FundDonation $d) => $d->category ?? $fund->label())
            ->map(fn ($group) => (int) $group->sum('amount'))->sortDesc()->all();

        return ['donations' => $donations, 'byCategory' => $byCategory, 'total' => (int) $donations->sum('amount')];
    }
}
