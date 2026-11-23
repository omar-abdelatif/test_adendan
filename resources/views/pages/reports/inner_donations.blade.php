@php
    $kinds = ['تبرع تنمية' => $donationDevSum, 'تبرع إنتساب' => $donationAffiliateSum, 'مقابر قديمة' => $donationTombsSum, 'ص.مقر' => $donationHeadquartersSum, 'ص.سيارة' => $donationDeathCarSum];
    $total = $donations->sum('amount');
    $max = max(1, ...array_values($kinds));
    $label = fn ($d) => $d->donation_type === 'أخرى' ? ($d->other_donation ?: 'أخرى') : ($d->donation_category ?: $d->donation_type);
@endphp
@extends('layouts.master')
@section('title', 'التبرعات الداخلية')
@section('content')
    <x-nb.page title="التبرعات الداخلية" subtitle="تبرعات الأعضاء حسب البند، والمستحقات اللي لسه ما اتدفعتش" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="كل التبرعات الداخلية" :value="number_format($total)" unit="ج.م" icon="heart" main :note="number_format($donations->count()).' تبرع'" />
            <x-nb.stat label="تبرع تنمية" :value="number_format($donationDevSum)" unit="ج.م" icon="trending-up" tone="green" />
            <x-nb.stat label="ص.مقر" :value="number_format($donationHeadquartersSum)" unit="ج.م" icon="home" tone="gold" />
            <x-nb.stat label="مستحقات تبرعات لسه" :value="number_format($sumTotalDuesDonations)" unit="ج.م" icon="alert-circle" tone="terra" />
        </x-nb.stats>

        <div class="nb-split">
            <x-nb.card title="كل التبرعات" icon="list" flush>
                <x-slot:actions>
                    <div class="nb-chips">
                        <a href="#" class="nb-chip is-on" data-nb-filter="#inner-table" data-col="3" data-value="">الكل</a>
                        @foreach (array_keys($kinds) as $kind)
                            <a href="#" class="nb-chip" data-nb-filter="#inner-table" data-col="3" data-value="{{ $kind }}">{{ $kind }}</a>
                        @endforeach
                    </div>
                </x-slot:actions>
                <x-nb.table id="inner-table" :order="[[0, 'desc']]" :page-length="25">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>العضو</th>
                            <th>رقم العضوية</th>
                            <th>البند</th>
                            <th>رقم الإيصال</th>
                            <th class="is-end">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($donations as $donation)
                            <tr>
                                <td class="nb-num nb-nowrap">{{ $donation->payment_date ?? $donation->created_at?->format('Y-m-d') }}</td>
                                <td class="nb-strong">{{ $donation->subscribers?->name ?? '—' }}</td>
                                <td class="nb-num">{{ $donation->member_id }}</td>
                                <td><x-nb.badge tone="gold">{{ $label($donation) }}</x-nb.badge></td>
                                <td class="nb-num">{{ $donation->invoice_no ?? '—' }}</td>
                                <td class="is-end nb-in" data-order="{{ $donation->amount }}">{{ number_format($donation->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            </x-nb.card>

            <x-nb.card title="حسب البند" icon="pie-chart" tone="gold">
                <div class="nb-bars">
                    @foreach ($kinds as $kind => $sum)
                        <div class="nb-bars__row">
                            <span>{{ $kind }}</span>
                            <span class="nb-bars__track"><span style="width: {{ round($sum / $max * 100) }}%"></span></span>
                            <span class="nb-bars__value">{{ number_format($sum) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-nb.card>
        </div>
    </div>
@endsection
