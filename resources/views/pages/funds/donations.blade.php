@php
    use App\Modules\Finance\Domain\Enums\Fund;
    $crumbs = $fund === Fund::Sponsorship
        ? ['الكفالة' => route('sponsorship.cases.index'), 'المتبرعين' => route('funds.donors', $fund)]
        : ['المقابر' => route('cemeteries.index'), 'المتبرعين' => route('funds.donors', $fund)];
    $monthName = $month->locale('ar')->translatedFormat('F Y');
@endphp
@extends('layouts.master')
@section('title', 'تبرعات '.$fund->label())
@section('content')
    <x-nb.page :title="'تبرعات '.$fund->label()" :subtitle="'تبرعات '.$monthName.' وإجماليها حسب البند'" :crumbs="$crumbs">
        <x-slot:actions>
            @include('pages.funds.partials.month-bar', ['route' => 'funds.donations'])
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
            <x-nb.button variant="ghost" icon="download" :href="route('funds.donations.export', [$fund, 'month' => $month->format('Y-m')])">إكسل</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat :label="'إجمالي '.$monthName" :value="number_format($total)" unit="ج.م" icon="heart" main />
            <x-nb.stat label="عدد التبرعات" :value="number_format($donations->count())" icon="hash" tone="gold" />
            <x-nb.stat label="المتبرعين" :value="number_format($donations->pluck('donator_id')->unique()->count())" icon="users" tone="ink" />
        </x-nb.stats>

        @if (count($byCategory) > 1)
            <x-nb.card title="حسب البند" icon="pie-chart" flush>
                <div class="nb-list">
                    @foreach ($byCategory as $category => $sum)
                        <div class="nb-list__row">
                            <div class="nb-list__main"><strong>{{ $category }}</strong>
                                <span class="nb-meter__bar" role="img" aria-label="{{ $total ? round($sum / $total * 100) : 0 }}%"><span style="width: {{ $total ? round($sum / $total * 100) : 0 }}%"></span></span>
                            </div>
                            <span class="nb-num" style="font-weight: 800">{{ number_format($sum) }} ج.م</span>
                        </div>
                    @endforeach
                </div>
            </x-nb.card>
        @endif

        <x-nb.card :title="'تبرعات '.$monthName" icon="list" flush>
            @if ($donations->isEmpty())
                <x-nb.empty icon="calendar" :title="'مفيش تبرعات في '.$monthName" text="اختار شهر تاني، أو سجّل تبرع من صفحة المتبرعين.">
                    <x-nb.button variant="ghost" :href="route('funds.donors', $fund)">المتبرعين</x-nb.button>
                </x-nb.empty>
            @else
                <x-nb.table id="fund-donations" :order="[[0, 'desc']]" :page-length="50">
                    <thead>
                        <tr><th>التاريخ</th><th>المتبرع</th><th>الإيصال</th><th>البند</th><th>الشهور</th><th>اتدفع في</th><th class="is-end">المبلغ</th><th class="no-sort is-actions"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($donations as $donation)
                            <tr>
                                <td class="nb-num nb-nowrap" data-order="{{ $donation->donated_on->format('Y-m-d') }} {{ str_pad((string) $donation->id, 9, '0', STR_PAD_LEFT) }}">{{ $donation->donated_on->format('Y-m-d') }}</td>
                                <td class="nb-strong">{{ $donation->donor?->name ?? '—' }}</td>
                                <td class="nb-num">{{ $donation->invoice_no ?? '—' }}</td>
                                <td>{{ $donation->categoryLabel() ?? $fund->label() }}</td>
                                <td>{{ $donation->monthsLabel() }}</td>
                                <td><x-nb.badge :tone="$donation->account->value === 'bank' ? 'teal' : 'gold'">{{ $donation->account->label() }}</x-nb.badge></td>
                                <td class="is-end nb-num nb-in" data-order="{{ $donation->amount }}">{{ number_format($donation->amount) }}</td>
                                <td class="is-actions"><div class="nb-row-actions"><x-nb.icon-button icon="printer" label="الإيصال" :href="route('funds.donations.receipt', [$fund, $donation])" target="_blank" /></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection
