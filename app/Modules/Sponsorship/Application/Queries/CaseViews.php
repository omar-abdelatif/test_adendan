<?php

namespace App\Modules\Sponsorship\Application\Queries;

use App\Models\SponsorshipCase;
use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
use App\Modules\Sponsorship\Domain\Enums\BenefitType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** The cases page (cards with filters) and the sponsorship report. */
class CaseViews
{
    public const PER_PAGE = 12;

    public const SORTS = ['income' => 'الأقل دخلاً', 'new' => 'الأحدث', 'name' => 'الاسم'];

    /**
     * @param  array{q?: ?string, type?: ?string, period?: ?string, gov?: ?string, sort?: ?string}  $filters
     * @return LengthAwarePaginator<int, SponsorshipCase>
     */
    public function cards(array $filters): LengthAwarePaginator
    {
        $query = $this->filtered($filters)->with('files:id,case_id,kind');

        match ($filters['sort'] ?? 'income') {
            'new' => $query->latest('id'),
            'name' => $query->orderBy('name'),
            default => $query->orderByRaw('monthly_income + pension_income + other_income')->orderBy('name'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Counts for the filter buttons, over all cases.
     *
     * @return array{all: int, type: array<string, int>, period: array<string, int>}
     */
    public function counts(): array
    {
        $count = fn (string $column) => SponsorshipCase::query()->groupBy($column)->selectRaw("{$column} as k, COUNT(*) as n")->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();

        return ['all' => SponsorshipCase::count(), 'type' => $count('benefit_type'), 'period' => $count('benefit_period')];
    }

    /** @return list<string> */
    public function governorates(): array
    {
        return SponsorshipCase::query()->whereNotNull('governorate')->distinct()->orderBy('governorate')->pluck('governorate')->all();
    }

    /**
     * The sponsorship report: cases by benefit and period, by governorate, and the cash cases.
     *
     * @return array{matrix: array<string, array<string, int>>, byGovernorate: array<string, int>, cash: Collection<int, SponsorshipCase>, total: int, people: int}
     */
    public function report(): array
    {
        $matrix = [];
        foreach (BenefitType::cases() as $type) {
            foreach (BenefitPeriod::cases() as $period) {
                $matrix[$type->value][$period->value] = 0;
            }
        }
        SponsorshipCase::query()->groupBy('benefit_type', 'benefit_period')
            ->selectRaw('benefit_type, benefit_period, COUNT(*) as n')->toBase()->get()
            ->each(function ($row) use (&$matrix) {
                $matrix[$row->benefit_type][$row->benefit_period] = (int) $row->n;
            });

        return [
            'matrix' => $matrix,
            'byGovernorate' => SponsorshipCase::query()->groupBy('governorate')->selectRaw("COALESCE(governorate, '—') as g, COUNT(*) as n")
                ->orderByDesc('n')->pluck('n', 'g')->map(fn ($n) => (int) $n)->all(),
            'cash' => SponsorshipCase::query()->where('benefit_type', BenefitType::Cash->value)
                ->orderBy('benefit_period')->orderByRaw('monthly_income + pension_income + other_income')->get(),
            'total' => SponsorshipCase::count(),
            // The cases themselves and their children.
            'people' => (int) SponsorshipCase::query()->selectRaw('COUNT(*) + COALESCE(SUM(sons + daughters), 0) as n')->value('n'),
        ];
    }

    /** @param  array{q?: ?string, type?: ?string, period?: ?string, gov?: ?string}  $filters
     * @return Builder<SponsorshipCase>
     */
    private function filtered(array $filters): Builder
    {
        $query = SponsorshipCase::query()->search($filters['q'] ?? null);
        if ($type = BenefitType::tryFrom((string) ($filters['type'] ?? ''))) {
            $query->where('benefit_type', $type->value);
        }
        if ($period = BenefitPeriod::tryFrom((string) ($filters['period'] ?? ''))) {
            $query->where('benefit_period', $period->value);
        }
        if (! empty($filters['gov'])) {
            $query->where('governorate', $filters['gov']);
        }

        return $query;
    }
}
