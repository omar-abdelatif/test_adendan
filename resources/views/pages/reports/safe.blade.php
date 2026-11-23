@php($isIn = fn ($t) => $t->transaction_cat === 'ايداع')
@extends('layouts.master')
@section('title', 'تقرير الخزنة')
@section('content')
    <x-nb.page title="الخزنة" subtitle="كل اللي دخل وخرج من الخزنة" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
            <x-nb.button variant="gold" icon="repeat" open="#safe-to-bank">تحويل للبنك</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="صافي الخزنة الحالي" :value="number_format($netSafeAmount)" unit="ج.م" icon="archive" main />
            <x-nb.stat label="الإشتراكات المحصلة" :value="number_format($sumSubscriptionsAmount)" unit="ج.م" icon="calendar" tone="green" />
            <x-nb.stat label="التبرعات المحصلة" :value="number_format($sumDonationsAmount)" unit="ج.م" icon="heart" tone="gold" />
            <x-nb.stat label="إجمالي الخارج" :value="number_format($withdrawalsAmount)" unit="ج.م" icon="arrow-up-right" tone="terra" note="مصروفات وتحويلات للبنك" />
        </x-nb.stats>

        <x-nb.card title="حركات الخزنة" :subtitle="number_format($transactions->count()).' حركة'" icon="list" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#safe-table" data-col="3" data-value="">الكل</a>
                    <a href="#" class="nb-chip" data-nb-filter="#safe-table" data-col="3" data-value="ايداع">داخل</a>
                    <a href="#" class="nb-chip" data-nb-filter="#safe-table" data-col="3" data-value="سحب">خارج</a>
                </div>
            </x-slot:actions>
            <x-nb.table id="safe-table" :order="[[0, 'desc']]" :page-length="25">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>البند</th>
                        <th>الاتجاه</th>
                        <th>عضوية</th>
                        <th>الإيصال</th>
                        <th class="is-end">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td class="nb-num nb-nowrap" data-order="{{ $transaction->payment_date }} {{ str_pad((string) $transaction->id, 9, '0', STR_PAD_LEFT) }}">{{ $transaction->payment_date ?? '—' }}</td>
                            <td><x-nb.badge tone="teal">{{ $transaction->transaction_type }}</x-nb.badge></td>
                            <td>{{ $transaction->item }}</td>
                            <td>
                                @if ($isIn($transaction))
                                    <x-nb.badge tone="green" dot>ايداع</x-nb.badge>
                                @else
                                    <x-nb.badge tone="terra" dot>سحب</x-nb.badge>
                                @endif
                            </td>
                            <td class="nb-num">{{ $transaction->member_id ?? '—' }}</td>
                            <td>
                                @if ($transaction->proof_url)
                                    <a href="{{ $transaction->proof_url }}" target="_blank" rel="noopener" class="nb-row"><i data-feather="paperclip"></i>إثبات</a>
                                @else
                                    <span class="nb-num">{{ $transaction->inv_no ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="is-end nb-nowrap {{ $isIn($transaction) ? 'nb-in' : 'nb-out' }}" data-order="{{ $transaction->amount }}">{{ $isIn($transaction) ? '+' : '−' }}{{ number_format($transaction->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="safe-to-bank" title="تحويل مبلغ من الخزنة إلى البنك" icon="repeat" :action="route('withdraw')" files submit="تأكيد التحويل">
        <div class="nb-form-grid">
            <x-nb.field label="المبلغ" name="amount" type="number" min="1" :max="$netSafeAmount" required :hint="'المتاح في الخزنة '.number_format($netSafeAmount).' ج.م'" />
            <x-nb.field label="تاريخ التحويل" name="transaction_date" type="date" :value="now()->toDateString()" />
            <x-nb.field label="إثبات الإيداع (صورة)" name="proof_img" type="file" accept="image/*" required span="full" />
            <div class="nb-alert nb-span-full"><i data-feather="info"></i><span>هتتسجل حركتين: سحب من الخزنة وإيداع في البنك بنفس المبلغ.</span></div>
        </div>
    </x-nb.modal>
@endpush
