@php
    $isIn = fn ($t) => $t->transaction_cat === 'ايداع' || $t->transaction_type === 'بنك/ايداع';
    $in = $transactions->filter($isIn);
    $out = $transactions->reject($isIn);
@endphp
@extends('layouts.master')
@section('title', 'تقرير البنك')
@section('content')
    <x-nb.page title="البنك" subtitle="رصيد حساب الجمعية في البنك وحركات الإيداع والسحب" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
            <x-nb.button variant="gold" icon="repeat" open="#bank-to-safe">سحب للخزنة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="رصيد البنك" :value="number_format($bankAmount)" unit="ج.م" icon="home" main />
            <x-nb.stat label="إيداعات" :value="number_format($in->sum('amount'))" unit="ج.م" icon="arrow-down-left" tone="green" :note="$in->count().' حركة'" />
            <x-nb.stat label="مسحوب للخزنة" :value="number_format($out->sum('amount'))" unit="ج.م" icon="arrow-up-right" tone="terra" :note="$out->count().' حركة'" />
        </x-nb.stats>

        <x-nb.card title="حركات البنك" icon="list" flush>
            @if ($transactions->isEmpty())
                <x-nb.empty icon="home" title="لسه مفيش حركات على البنك" />
            @else
                <x-nb.table id="bank-table" :order="[[0, 'desc']]">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>البيان</th>
                            <th>الاتجاه</th>
                            <th>الإثبات</th>
                            <th class="is-end">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $item)
                            <tr>
                                <td class="nb-num nb-nowrap">{{ $item->payment_date }}</td>
                                <td class="nb-strong">{{ $item->item }}</td>
                                <td>
                                    @if ($isIn($item))
                                        <x-nb.badge tone="green" dot>ايداع</x-nb.badge>
                                    @else
                                        <x-nb.badge tone="terra" dot>سحب</x-nb.badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->proof_url)
                                        <a href="{{ $item->proof_url }}" target="_blank" rel="noopener" class="nb-row"><i data-feather="paperclip"></i>عرض</a>
                                    @else
                                        <span class="nb-muted">—</span>
                                    @endif
                                </td>
                                <td class="is-end nb-nowrap {{ $isIn($item) ? 'nb-in' : 'nb-out' }}" data-order="{{ $item->amount }}">{{ $isIn($item) ? '+' : '−' }}{{ number_format($item->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="bank-to-safe" title="سحب مبلغ من البنك إلى الخزنة" icon="repeat" :action="route('bank.withdraw')" files submit="تأكيد السحب">
        <div class="nb-form-grid">
            <x-nb.field label="المبلغ" name="amount" type="number" min="1" :max="$bankAmount" required :hint="'الرصيد '.number_format($bankAmount).' ج.م'" />
            <x-nb.field label="تاريخ السحب" name="payment_date" type="date" :value="now()->toDateString()" />
            <x-nb.field label="إثبات السحب (صورة)" name="proof_img" type="file" accept="image/*" required span="full" />
        </div>
    </x-nb.modal>
@endpush
