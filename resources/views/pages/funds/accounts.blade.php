@php
    use App\Modules\Finance\Domain\Enums\Account;
    use App\Modules\Finance\Domain\Enums\Direction;
    use App\Modules\Finance\Domain\Enums\Fund;
    $crumbs = $fund === Fund::Sponsorship ? ['الكفالة' => route('sponsorship.cases.index')] : ['المقابر' => route('cemeteries.index')];
    $monthName = $month->locale('ar')->translatedFormat('F Y');
    $isIn = fn ($t) => $t->transaction_cat === Direction::In->value;
@endphp
@extends('layouts.master')
@section('title', 'خزنة '.$fund->label())
@section('content')
    <x-nb.page :title="'خزنة وبنك '.$fund->label()" :subtitle="'فلوس '.$fund->label().' لوحدها: مش بتتخلط برصيد الجمعية'" :crumbs="$crumbs">
        <x-slot:actions>
            @include('pages.funds.partials.month-bar', ['route' => 'funds.accounts'])
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
            <x-nb.button variant="ghost" icon="download" :href="route('funds.accounts.export', [$fund, 'month' => $month->format('Y-m')])">إكسل</x-nb.button>
            <x-nb.button variant="gold" icon="repeat" open="#fund-transfer">تحويل</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="رصيد الخزنة" :value="number_format($safe)" unit="ج.م" icon="archive" main />
            <x-nb.stat label="رصيد البنك" :value="number_format($bank)" unit="ج.م" icon="credit-card" tone="ink" />
            <x-nb.stat :label="'دخل في '.$monthName" :value="number_format($in)" unit="ج.م" icon="arrow-down-left" tone="green" note="من غير التحويلات بين الخزنة والبنك" />
            <x-nb.stat :label="'خرج في '.$monthName" :value="number_format($out)" unit="ج.م" icon="arrow-up-right" tone="terra" note="تصحيحات وإلغاء تبرعات" />
        </x-nb.stats>

        <x-nb.card :title="'حركات '.$monthName" :subtitle="number_format($transactions->count()).' حركة'" icon="list" flush>
            @if ($transactions->isEmpty())
                <x-nb.empty icon="list" :title="'مفيش حركات في '.$monthName" />
            @else
                <x-nb.table id="fund-transactions" :order="[[0, 'desc']]" :page-length="50">
                    <thead>
                        <tr><th>التاريخ</th><th>البند</th><th>الحساب</th><th>الاتجاه</th><th>الإيصال</th><th class="is-end">المبلغ</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="nb-num nb-nowrap" data-order="{{ $transaction->payment_date }} {{ str_pad((string) $transaction->id, 9, '0', STR_PAD_LEFT) }}">{{ $transaction->payment_date }}</td>
                                <td>{{ $transaction->item }}</td>
                                <td><x-nb.badge :tone="$transaction->account === Account::Bank->value ? 'teal' : 'gold'">{{ Account::tryFrom((string) $transaction->account)?->label() ?? '—' }}</x-nb.badge></td>
                                <td>@if ($isIn($transaction))<x-nb.badge tone="green" dot>ايداع</x-nb.badge>@else<x-nb.badge tone="terra" dot>سحب</x-nb.badge>@endif</td>
                                <td>
                                    @if ($transaction->proof_url)
                                        <a href="{{ $transaction->proof_url }}" target="_blank" rel="noopener" class="nb-row"><i data-feather="paperclip"></i>إثبات</a>
                                    @else
                                        <span class="nb-num">{{ $transaction->inv_no ?: '—' }}</span>
                                    @endif
                                </td>
                                <td class="is-end nb-nowrap {{ $isIn($transaction) ? 'nb-in' : 'nb-out' }}" data-order="{{ $transaction->amount }}">{{ $isIn($transaction) ? '+' : '−' }}{{ number_format($transaction->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="fund-transfer" :title="'تحويل في '.$fund->label()" icon="repeat" :action="route('funds.accounts.transfer', $fund)" files submit="تأكيد التحويل" :autoopen="$errors->has('amount') || $errors->has('proof_img')">
        <div class="nb-form-grid">
            <x-nb.choice label="من" name="from" :options="['safe' => 'الخزنة ← البنك', 'bank' => 'البنك ← الخزنة']" :value="old('from', 'safe')" required span="full" />
            <x-nb.field label="المبلغ" name="amount" type="number" min="1" required :hint="'في الخزنة '.number_format($safe).' · في البنك '.number_format($bank).' ج.م'" />
            <x-nb.field label="تاريخ التحويل" name="transaction_date" type="date" :value="now()->toDateString()" />
            <x-nb.field label="إثبات الإيداع أو السحب (صورة)" name="proof_img" type="file" accept="image/*" required span="full" />
            <div class="nb-alert nb-span-full"><i data-feather="info"></i><span>هتتسجل حركتين بنفس المبلغ جوه {{ $fund->label() }}: سحب من حساب وإيداع في التاني.</span></div>
        </div>
    </x-nb.modal>
@endpush
