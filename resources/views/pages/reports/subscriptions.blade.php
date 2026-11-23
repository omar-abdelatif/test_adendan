@php
    $currentRemaining = $sumTotalCurrentSubscriptionDues - $sumTotalCurrentSubscriptionDuesPaid;
    $currentPercent = $sumTotalCurrentSubscriptionDues > 0 ? (int) floor($sumTotalCurrentSubscriptionDuesPaid / $sumTotalCurrentSubscriptionDues * 100) : 0;
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—';
@endphp
@extends('layouts.master')
@section('title', 'تقارير الإشتراكات')
@section('content')
    <x-nb.page title="تقارير الإشتراكات" :subtitle="'التحصيل في سنة '.$currentSubscriptionYear.' والمستحقات من السنين اللي فاتت'" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat :label="'المحصل من اشتراك '.$currentSubscriptionYear" :value="number_format($sumTotalCurrentSubscriptionDuesPaid)" unit="ج.م" icon="check-circle" main
                       :note="'من '.number_format($sumTotalCurrentSubscriptionDues).' ج.م · '.$currentPercent.'%'" />
            <x-nb.stat :label="'الباقي من اشتراك '.$currentSubscriptionYear" :value="number_format($currentRemaining)" unit="ج.م" icon="clock" tone="gold" :progress="$currentPercent" />
            <x-nb.stat label="مستحقات السنين اللي فاتت" :value="number_format($sumTotalSubscriptionDues)" unit="ج.م" icon="alert-circle" tone="terra" />
            <x-nb.stat label="عدد الأعضاء" :value="number_format($count)" icon="users" tone="green" :note="'المحصل من كل المستحقات '.number_format($subscriptionSumDuePaied).' ج.م'" />
        </x-nb.stats>

        <x-nb.card title="الأعضاء ومدفوعات الإشتراك" subtitle="اضغط على السهم عشان تشوف مدفوعات العضو" icon="users" flush>
            <x-nb.table id="subs-table" :order="[[1, 'asc']]">
                <thead>
                    <tr>
                        <th class="no-sort"></th>
                        <th>رقم العضوية</th>
                        <th>العضو</th>
                        <th>رقم المحمول</th>
                        <th class="is-end">مدفوعات الإشتراك</th>
                        <th>آخر سنة مدفوعة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscribers as $subscriber)
                        @php($payments = $subscriber->paymentTransactions)
                        <tr>
                            <td>
                                <button type="button" class="nb-icon-btn" data-nb-child aria-label="مدفوعات {{ $subscriber->name }}"><i data-feather="chevron-down"></i></button>
                                <template>
                                    @if ($payments->isEmpty())
                                        <div class="nb-muted" style="padding: 8px 12px">مفيش مدفوعات إشتراك مسجلة.</div>
                                    @else
                                        <table class="nb-table">
                                            <thead><tr><th>فترة الإشتراك</th><th>تاريخ الدفع</th><th>رقم الإيصال</th><th class="is-end">المبلغ</th></tr></thead>
                                            <tbody>
                                                @foreach ($payments as $subscription)
                                                    <tr><td>{{ $subscription->item }}</td><td>{{ $subscription->payment_date ?? $subscription->created_at?->format('Y-m-d') }}</td><td>{{ $subscription->inv_no ?? '—' }}</td><td class="is-end">{{ number_format($subscription->amount) }}</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </template>
                            </td>
                            <td class="nb-num">{{ $subscriber->member_id }}</td>
                            <td class="nb-strong">{{ $subscriber->name }}</td>
                            <td class="nb-num" dir="ltr">{{ $phone($subscriber->mobile_no) }}</td>
                            <td class="is-end nb-in" data-order="{{ $payments->sum('amount') }}">{{ number_format($payments->sum('amount')) }}</td>
                            <td>{{ $payments->max('item') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection
