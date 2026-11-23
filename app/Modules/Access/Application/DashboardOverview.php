<?php

namespace App\Modules\Access\Application;

use App\Models\CostYears;
use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Models\UserUpdateStaging;
use App\Modules\Finance\Application\Treasury;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\FiscalCalendar;
use App\Modules\Members\Domain\MemberStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The numbers on the dashboard home page. Each section is built only when the
 * signed-in staff member may see it (the controller decides).
 */
class DashboardOverview
{
    private const MONTHS = [
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
    ];

    public function __construct(
        private readonly Treasury $treasury,
        private readonly FiscalCalendar $calendar,
    ) {}

    /** @return array{safe: int, bank: int, today: int, todayCount: int} */
    public function balances(): array
    {
        $today = PaymentTransaction::query()
            ->where('transaction_cat', Direction::In->value)
            ->whereNotIn('transaction_type', TransactionType::transferValues())
            ->whereDate('payment_date', now()->toDateString());

        return [
            'safe' => $this->treasury->balance(Account::Safe)->toPounds(),
            'bank' => $this->treasury->balance(Account::Bank)->toPounds(),
            'today' => (int) (clone $today)->sum('amount'),
            'todayCount' => (clone $today)->count(),
        ];
    }

    /**
     * How much of the current subscription year has been collected, and what is
     * still owed from earlier years.
     *
     * @return array{year: string|null, percent: int, paidMembers: int, chargedMembers: int, overdue: int, overdueMembers: int}
     */
    public function collection(): array
    {
        $year = CostYears::max('year');
        $year = $year === null ? null : (string) $year;
        $subscriptionDues = fn () => Due::query()->where('transaction_type', DueType::Subscription->value);

        $current = $year === null ? null : $subscriptionDues()->where('item', $year)
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(amount_paid), 0) AS paid, COUNT(*) AS charged, COALESCE(SUM(amount_remaining <= 0), 0) AS settled')
            ->toBase()
            ->first();

        $overdue = $subscriptionDues()
            ->when($year !== null, fn ($q) => $q->where('item', '!=', $year))
            ->where('amount_remaining', '>', 0)
            ->selectRaw('COALESCE(SUM(amount_remaining), 0) AS remaining, COUNT(DISTINCT member_id) AS members')
            ->toBase()
            ->first();

        $total = (int) ($current->total ?? 0);

        return [
            'year' => $year,
            'percent' => $total > 0 ? (int) floor(((int) $current->paid) / $total * 100) : 0,
            'paidMembers' => (int) ($current->settled ?? 0),
            'chargedMembers' => (int) ($current->charged ?? 0),
            'overdue' => (int) ($overdue->remaining ?? 0),
            'overdueMembers' => (int) ($overdue->members ?? 0),
        ];
    }

    /** @return array{active: int, total: int, newThisMonth: int} */
    public function members(): array
    {
        return [
            'active' => Subscribers::where('status', MemberStatus::Active->value)->count(),
            'total' => Subscribers::query()->billable()->count(),
            'newThisMonth' => Subscribers::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /** @return Collection<int, PaymentTransaction> */
    public function recentTransactions(int $limit = 6): Collection
    {
        return PaymentTransaction::query()
            ->with('subscriber:id,member_id,name')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @return array{count: int, latest: Collection<int, UserUpdateStaging>} */
    public function pendingUpdateRequests(int $limit = 4): array
    {
        return [
            'count' => UserUpdateStaging::count(),
            'latest' => UserUpdateStaging::latest('id')->limit($limit)->get(['id', 'name', 'member_id', 'created_at']),
        ];
    }

    /**
     * Money received each month of the current subscription year (July to June).
     *
     * @return array{year: int, max: int, months: list<array{name: string, amount: int, current: bool, future: bool}>}
     */
    public function monthlyIncome(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now();
        $fiscalYear = $this->calendar->subscriptionYear($today);
        $start = CarbonImmutable::create($fiscalYear - 1, 7, 1);
        $end = $start->addYear();

        $totals = PaymentTransaction::query()
            ->where('transaction_cat', Direction::In->value)
            ->whereNotIn('transaction_type', TransactionType::transferValues())
            ->where('payment_date', '>=', $start->toDateString())
            ->where('payment_date', '<', $end->toDateString())
            ->selectRaw('YEAR(payment_date) AS y, MONTH(payment_date) AS m, SUM(amount) AS total')
            ->groupBy('y', 'm')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [sprintf('%d-%d', $row->y, $row->m) => (int) $row->total]);

        $months = [];
        for ($month = $start; $month < $end; $month = $month->addMonth()) {
            $key = sprintf('%d-%d', $month->year, $month->month);
            $months[] = [
                'name' => self::MONTHS[$month->month],
                'amount' => $totals[$key] ?? 0,
                'current' => $month->isSameMonth($today),
                'future' => $month->greaterThan($today->startOfMonth()),
            ];
        }

        return [
            'year' => $fiscalYear,
            'max' => max(1, ...array_column($months, 'amount')),
            'months' => $months,
        ];
    }
}
