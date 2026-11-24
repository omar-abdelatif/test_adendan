{{-- One donor's donations to the fund, loaded into the #donor-history drawer. --}}
@php
    use App\Modules\Finance\Domain\Enums\Account;
    $phone = $donor->mobile_number ? '0'.ltrim((string) $donor->mobile_number, '0') : null;
@endphp
<div class="nb-stack-sm">
    <div class="nb-casefile__head">
        <x-nb.avatar :name="$donor->name" />
        <div>
            <strong>{{ $donor->name }}</strong>
            <span class="nb-muted" style="font-size: 12.5px">@if ($phone)<a href="tel:{{ $phone }}" dir="ltr">{{ $phone }}</a> · @endif{{ $donor->donator_type }}@if ($donor->duration) · {{ $donor->duration }}@endif</span>
        </div>
    </div>

    <div class="nb-income" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
        <div><span>تبرعاته للصندوق</span><strong class="nb-num">{{ $donations->count() }}</strong></div>
        <div class="is-total"><span>الإجمالي</span><strong class="nb-num">{{ number_format($donations->sum('amount')) }} ج.م</strong></div>
    </div>
    @if ($elsewhere)
        <p class="nb-muted" style="margin: 0; font-size: 12.5px">وليه كمان {{ $elsewhere }} {{ $elsewhere >= 2 && $elsewhere <= 10 ? 'تبرعات' : 'تبرع' }} في صناديق تانية.</p>
    @endif

    <button type="button" class="nb-btn" data-donation-open='@json(["mode" => "add", "donator_id" => $donor->id])'><i data-feather="plus-circle"></i>تبرع جديد</button>

    <h3 class="nb-ops__title">التبرعات</h3>
    @forelse ($donations as $donation)
        @php
            $edit = [
                'mode' => 'edit', 'url' => route('funds.donations.update', [$fund, $donation]), 'donator_id' => $donation->donator_id,
                'amount' => $donation->amount, 'account' => $donation->account->value, 'invoice_no' => $donation->invoice_no,
                'category' => $donation->category, 'category_other' => $donation->category_other, 'months' => $donation->months ?? [],
                'donated_on' => $donation->donated_on->format('Y-m-d'), 'notes' => $donation->notes,
            ];
            $remove = ['url' => route('funds.donations.destroy', [$fund, $donation]), 'text' => 'هتلغي تبرع '.number_format($donation->amount).' ج.م من '.$donor->name.'؟'];
        @endphp
        <div class="nb-card nb-item">
            <span class="nb-item__icon" aria-hidden="true"><i data-feather="{{ $donation->account === Account::Bank ? 'credit-card' : 'archive' }}"></i></span>
            <div class="nb-item__main">
                <strong>{{ $donation->categoryLabel() ?? $fund->label() }}</strong>
                <span>{{ $donation->donated_on->format('Y-m-d') }}@if ($donation->invoice_no) · إيصال {{ $donation->invoice_no }}@endif · {{ $donation->monthsLabel() }} · {{ $donation->account->label() }}</span>
            </div>
            <span class="nb-item__amount nb-num">{{ number_format($donation->amount) }}</span>
            <span class="nb-item__acts">
                <a class="nb-icon-btn" href="{{ route('funds.donations.receipt', [$fund, $donation]) }}" target="_blank" rel="noopener" title="الإيصال"><i data-feather="printer"></i></a>
                <button type="button" class="nb-icon-btn" title="تعديل" data-donation-open='@json($edit)'><i data-feather="edit-2"></i></button>
                <button type="button" class="nb-icon-btn nb-icon-btn--danger" title="إلغاء التبرع" data-donation-delete='@json($remove)'><i data-feather="trash-2"></i></button>
            </span>
        </div>
    @empty
        <p class="nb-muted" style="margin: 0">لسه مفيش تبرعات منه للصندوق ده.</p>
    @endforelse
</div>
