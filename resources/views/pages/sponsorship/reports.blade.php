@php
    use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
    use App\Modules\Sponsorship\Domain\Enums\BenefitType;
    $byType = fn (BenefitType $t) => array_sum($matrix[$t->value]);
    $byPeriod = fn (BenefitPeriod $p) => array_sum(array_column($matrix, $p->value));
@endphp
@extends('layouts.master')
@section('title', 'تقرير الكفالة')
@section('content')
    <x-nb.page title="تقرير الكفالة" subtitle="عدد الحالات حسب نوع الاستفادة ومدتها، والمحافظات، والحالات النقدية بدخلها" :crumbs="['الكفالة' => route('sponsorship.cases.index')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
            <x-nb.button variant="ghost" icon="download" :href="route('sponsorship.reports.export')">كل الحالات إكسل</x-nb.button>
            @can('الكفالة')
                <x-nb.button icon="calendar" :href="route('funds.donations', 'sponsorship')">تبرعات الشهر</x-nb.button>
            @endcan
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="الحالات" :value="number_format($total)" icon="users" main />
            <x-nb.stat label="نقدية" :value="number_format($byType(BenefitType::Cash))" icon="credit-card" />
            <x-nb.stat label="عينية" :value="number_format($byType(BenefitType::InKind))" icon="gift" tone="gold" />
            <x-nb.stat label="الأفراد" :value="number_format($people)" icon="home" tone="ink" note="الحالات وولادهم" />
        </x-nb.stats>

        <x-nb.card title="حسب نوع الاستفادة ومدتها" icon="grid">
            <div class="nb-quad">
                @foreach (BenefitType::cases() as $type)
                    @foreach (BenefitPeriod::cases() as $period)
                        <a href="{{ route('sponsorship.cases.index', ['type' => $type->value, 'period' => $period->value]) }}" @class(['nb-card', 'nb-quad__cell', 'is-cash' => $type === BenefitType::Cash])>
                            <span class="nb-card__icon {{ $type === BenefitType::Cash ? '' : 'nb-tone-gold' }}" aria-hidden="true"><i data-feather="{{ $type === BenefitType::Cash ? 'credit-card' : 'gift' }}"></i></span>
                            <span><b>{{ $type->label() }} · {{ $period->label() }}</b><br><span class="nb-muted" style="font-size: 12.5px">{{ $type === BenefitType::Cash ? 'مبلغ' : 'مواد غذائية وكسوة' }} {{ $period === BenefitPeriod::Monthly ? 'كل شهر' : 'في المواسم' }}</span></span>
                            <strong class="nb-num">{{ $matrix[$type->value][$period->value] }}</strong>
                        </a>
                    @endforeach
                @endforeach
            </div>
            <p class="nb-muted" style="margin: 12px 0 0; font-size: 13px">شهرية {{ $byPeriod(BenefitPeriod::Monthly) }} · موسمية {{ $byPeriod(BenefitPeriod::Seasonal) }}</p>
        </x-nb.card>

        <div class="nb-cols" style="display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: start">
            <x-nb.card title="الحالات النقدية ودخلها" :subtitle="$cash->count().' حالة · إجمالي دخلهم '.number_format($cash->sum(fn ($c) => $c->totalIncome())).' ج.م في الشهر'" icon="credit-card" flush>
                @if ($cash->isEmpty())
                    <x-nb.empty icon="credit-card" title="مفيش حالات نقدية" />
                @else
                    <x-nb.table plain>
                        <thead><tr><th>الحالة</th><th>المدة</th><th>الأفراد</th><th class="is-end">الدخل</th></tr></thead>
                        <tbody>
                            @foreach ($cash as $case)
                                <tr>
                                    <td class="nb-strong">{{ $case->name }}</td>
                                    <td>{{ $case->benefit_period->label() }}</td>
                                    <td class="nb-num">{{ $case->children() + 1 }}</td>
                                    <td class="is-end nb-num">{{ number_format($case->totalIncome()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-nb.table>
                @endif
            </x-nb.card>

            <x-nb.card title="حسب المحافظة" icon="map-pin" flush>
                @if (! $byGovernorate)
                    <x-nb.empty icon="map-pin" title="مفيش حالات لسه" />
                @else
                    <div class="nb-list">
                        @foreach ($byGovernorate as $gov => $n)
                            <div class="nb-list__row">
                                <div class="nb-list__main"><strong>{{ $gov }}</strong>
                                    <span class="nb-meter__bar" role="img" aria-label="{{ $n }} حالة"><span style="width: {{ $total ? round($n / $total * 100) : 0 }}%"></span></span>
                                </div>
                                <span class="nb-num" style="font-weight: 800">{{ $n }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nb.card>
        </div>
    </div>
@endsection
