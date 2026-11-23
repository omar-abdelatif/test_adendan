@php
    $rows = collect($queryResult ?? $delays ?? []);
    $owing = $rows->filter(fn ($r) => (int) $r->amount_remaining > 0);
    $searched = isset($delays);
@endphp
@extends('layouts.master')
@section('title', 'متأخرات الإشتراك')
@section('content')
    <x-nb.page title="متأخرات الإشتراك" subtitle="مستحقات الإشتراك على الأعضاء، وبحث بقيمة المستحق" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.card title="بحث بقيمة المستحق" icon="search">
            <form action="{{ route('reports.search') }}" method="GET" class="nb-row" style="align-items: flex-end">
                <x-nb.field label="المقارنة" name="optype" type="select" :options="['>' => 'أكبر من', '<' => 'أقل من', '=' => 'يساوي']" :value="request('optype', '>')" :empty="false" style="width: 160px" />
                <x-nb.field label="قيمة المستحق" name="amount" type="number" min="0" :value="request('amount')" style="width: 200px" />
                <x-nb.button type="submit" icon="search">بحث</x-nb.button>
                @if ($searched)
                    <x-nb.button variant="ghost" :href="route('reports.search')">مسح البحث</x-nb.button>
                @endif
            </form>
        </x-nb.card>

        <x-nb.stats>
            <x-nb.stat label="إجمالي الباقي" :value="number_format($owing->sum(fn ($r) => (int) $r->amount_remaining))" unit="ج.م" icon="alert-circle" tone="terra" />
            <x-nb.stat label="أعضاء عليهم باقي" :value="number_format($owing->pluck('member_id')->unique()->count())" icon="users" tone="gold" />
            <x-nb.stat label="المدفوع من المستحقات" :value="number_format($rows->sum(fn ($r) => (int) $r->amount_paid))" unit="ج.م" icon="check-circle" tone="green" />
        </x-nb.stats>

        <x-nb.card :title="$searched ? 'نتيجة البحث' : 'كل مستحقات الإشتراك'" :subtitle="number_format($rows->count()).' مستحق'" icon="list" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip" data-nb-filter="#delays-table" data-col="6" data-value="">الكل</a>
                    <a href="#" class="nb-chip is-on" data-nb-filter="#delays-table" data-col="6" data-value="عليه باقي">عليه باقي</a>
                    <a href="#" class="nb-chip" data-nb-filter="#delays-table" data-col="6" data-value="مسدد">مسدد</a>
                </div>
            </x-slot:actions>
            @if ($rows->isEmpty())
                <x-nb.empty icon="check-circle" :title="$noOldDelays ?? 'مفيش مستحقات'" />
            @else
                <x-nb.table id="delays-table" :order="[[5, 'desc']]" :page-length="25">
                    <thead>
                        <tr>
                            <th>العضو</th>
                            <th>رقم العضوية</th>
                            <th>البند</th>
                            <th class="is-end">المطلوب</th>
                            <th class="is-end">المدفوع</th>
                            <th class="is-end">الباقي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($remaining = (int) $row->amount_remaining)
                            <tr>
                                <td class="nb-strong">{{ $row->name }}</td>
                                <td class="nb-num">{{ $row->member_id }}</td>
                                <td>{{ $row->item }}</td>
                                <td class="is-end nb-num">{{ number_format($row->total_amount) }}</td>
                                <td class="is-end nb-num">{{ number_format((int) $row->amount_paid) }}</td>
                                <td class="is-end {{ $remaining ? 'nb-out' : 'nb-muted' }}" data-order="{{ $remaining }}">{{ number_format($remaining) }}</td>
                                <td>{!! $remaining > 0 ? '<span class="nb-badge nb-badge--terra">عليه باقي</span>' : '<span class="nb-badge nb-badge--green">مسدد</span>' !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection
@push('scripts')
    <script>$(function () { $('[data-nb-filter="#delays-table"].is-on').trigger('click'); });</script>
@endpush
