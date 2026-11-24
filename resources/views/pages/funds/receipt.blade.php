{{-- A donation receipt for the sponsorship or new-tombs fund, ready to print (A5, two copies on one A4). --}}
@php
    $phone = $donation->donor?->mobile_number ? '0'.ltrim((string) $donation->donor->mobile_number, '0') : null;
    $date = $donation->donated_on->locale('ar')->translatedFormat('j F Y');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>إيصال تبرع {{ $donation->invoice_no ?? $donation->id }} · جمعية أدندان الخيرية</title>
    @include('layouts.assets.css')
    <style>
        body { margin: 0; background: var(--nb-cream); font: 500 15px/1.7 'Cairo', sans-serif; color: var(--nb-ink); }
        .rc-tools { display: flex; gap: 10px; justify-content: center; padding: 18px; }
        .rc-sheet { width: min(760px, calc(100% - 32px)); margin: 0 auto 32px; display: flex; flex-direction: column; gap: 18px; }
        .rc { background: #FFFFFF; border: 1px solid var(--nb-border); border-radius: 18px; padding: 22px 26px; display: flex; flex-direction: column; gap: 14px; break-inside: avoid; }
        .rc__head { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid var(--nb-teal); padding-bottom: 12px; }
        .rc__head img { width: 54px; height: 60px; object-fit: cover; border-radius: 999px 999px 12px 12px; }
        .rc__head strong { display: block; font-size: 18px; }
        .rc__head span { color: var(--nb-muted); font-size: 13px; }
        .rc__no { margin-inline-start: auto; text-align: center; }
        .rc__no b { display: block; font-size: 20px; color: var(--nb-teal); }
        .rc__amount { display: flex; align-items: baseline; gap: 10px; padding: 12px 16px; border-radius: 12px; background: var(--nb-teal-soft); }
        .rc__amount b { font-size: 28px; color: var(--nb-teal); }
        .rc dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; }
        .rc dt { color: var(--nb-muted); font-weight: 700; }
        .rc dd { margin: 0; font-weight: 700; }
        .rc__sign { display: flex; flex-wrap: wrap; gap: 12px 24px; justify-content: space-between; padding-top: 22px; color: var(--nb-muted); font-size: 13px; }
        .rc__sign span { flex: 1 1 120px; max-width: 200px; border-top: 1px dashed var(--nb-input); padding-top: 6px; text-align: center; }
        @media (max-width: 560px) { .rc { padding: 18px 16px; } .rc__head { flex-wrap: wrap; } .rc__amount { flex-wrap: wrap; } }
        .rc__copy { font-size: 12px; color: var(--nb-muted); }
        @media print {
            @page { size: A4; margin: 10mm; }
            body { background: #FFFFFF; }
            .rc-tools { display: none; }
            .rc-sheet { width: 100%; margin: 0; gap: 10mm; }
            .rc { border-color: #BBB; }
        }
    </style>
</head>
<body>
    <div class="rc-tools">
        <button type="button" class="nb-btn" onclick="window.print()"><i data-feather="printer"></i>اطبع</button>
        <a class="nb-btn nb-btn--ghost" href="{{ route('funds.donors', $fund) }}">رجوع للمتبرعين</a>
    </div>
    <div class="rc-sheet">
        @foreach (['نسخة المتبرع', 'نسخة الجمعية'] as $copy)
            <section class="rc" aria-label="{{ $copy }}">
                <header class="rc__head">
                    <img src="{{ asset('assets/images/nubian/logo-arch.jpg') }}" alt="">
                    <div><strong>جمعية أدندان الخيرية</strong><span>إيصال تبرع · {{ $fund->label() }}</span></div>
                    <div class="rc__no"><span class="rc__copy">رقم الإيصال</span><b class="nb-num">{{ $donation->invoice_no ?? '—' }}</b></div>
                </header>
                <div class="rc__amount"><span>استلمنا مبلغ</span><b class="nb-num">{{ number_format($donation->amount) }}</b><span>جنيه مصري</span></div>
                <dl>
                    <dt>من السيد/ة</dt><dd>{{ $donation->donor?->name ?? '—' }}@if ($phone) · <span dir="ltr">{{ $phone }}</span>@endif</dd>
                    <dt>البند</dt><dd>{{ $donation->categoryLabel() ?? 'تبرع لصندوق '.$fund->label() }}</dd>
                    <dt>عن</dt><dd>{{ $donation->months ? $donation->monthsLabel().' '.$donation->donated_on->year : 'تبرع مرة واحدة' }}</dd>
                    <dt>اتدفع</dt><dd>{{ $donation->account->value === 'bank' ? 'تحويل بنكي' : 'كاش في الخزنة' }}</dd>
                    <dt>التاريخ</dt><dd>{{ $date }}</dd>
                    @if ($donation->notes)
                        <dt>ملاحظات</dt><dd>{{ $donation->notes }}</dd>
                    @endif
                </dl>
                <div class="rc__sign"><span>توقيع المستلم</span><span class="rc__copy">{{ $copy }}</span><span>ختم الجمعية</span></div>
            </section>
        @endforeach
    </div>
    <script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script>if (window.feather) feather.replace();</script>
</body>
</html>
