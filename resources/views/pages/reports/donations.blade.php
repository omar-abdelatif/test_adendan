@php
    $total = $donators->sum('amount');
    $top = $donators->groupBy('name')->map(fn ($g) => $g->sum('amount'))->sortDesc()->take(6);
    $max = max(1, $top->max() ?? 1);
@endphp
@extends('layouts.master')
@section('title', 'التبرعات الخارجية')
@section('content')
    <x-nb.page title="التبرعات الخارجية" subtitle="تبرعات المتبرعين من بره العضوية" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="gift" :href="route('donators.all')">المتبرعين</x-nb.button>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="إجمالي التبرعات الخارجية" :value="number_format($total)" unit="ج.م" icon="gift" main :note="number_format($donators->count()).' تبرع'" />
            <x-nb.stat label="من متبرعين منتظمين" :value="number_format($donators->where('donator_type', 'منتظم')->sum('amount'))" unit="ج.م" icon="repeat" tone="green" />
            <x-nb.stat label="من متبرعين موسميين" :value="number_format($donators->where('donator_type', 'موسمي')->sum('amount'))" unit="ج.م" icon="sun" tone="gold" />
        </x-nb.stats>

        <div class="nb-split">
            <x-nb.card title="كل التبرعات" icon="list" flush>
                <x-nb.table id="outer-table" :order="[[0, 'desc']]" :page-length="25">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المتبرع</th>
                            <th>النوع</th>
                            <th>جهة التبرع</th>
                            <th>المدة</th>
                            <th>رقم الإيصال</th>
                            <th class="is-end">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($donators as $donator)
                            <tr>
                                <td class="nb-num nb-nowrap">{{ $donator->created_at?->format('Y-m-d') }}</td>
                                <td class="nb-strong">{{ $donator->name }}</td>
                                <td><x-nb.badge :tone="$donator->donator_type === 'منتظم' ? 'green' : 'gold'">{{ $donator->donator_type }}</x-nb.badge></td>
                                <td>{{ $donator->donation_destination }}</td>
                                <td>{{ $donator->donators?->duration ?? $donator->duration ?? '—' }}</td>
                                <td class="nb-num">{{ $donator->invoice_id }}</td>
                                <td class="is-end nb-in" data-order="{{ $donator->amount }}">{{ number_format($donator->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            </x-nb.card>

            <x-nb.card title="أكتر المتبرعين" icon="award" tone="gold">
                @if ($top->isEmpty())
                    <x-nb.empty icon="gift" title="لسه مفيش تبرعات" />
                @else
                    <div class="nb-bars">
                        @foreach ($top as $name => $sum)
                            <div class="nb-bars__row">
                                <span>{{ $name }}</span>
                                <span class="nb-bars__track"><span style="width: {{ round($sum / $max * 100) }}%"></span></span>
                                <span class="nb-bars__value">{{ number_format($sum) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nb.card>
        </div>
    </div>
@endsection
