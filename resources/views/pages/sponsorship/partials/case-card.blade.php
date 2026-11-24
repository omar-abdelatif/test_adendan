@php
    use App\Modules\Sponsorship\Domain\Enums\BenefitType;
    $cash = $case->benefit_type === BenefitType::Cash;
    $missing = $case->missing();
@endphp
<article @class(['nb-card', 'nb-case', 'is-cash' => $cash])>
    <div class="nb-case__head">
        <x-nb.avatar :name="$case->name" :tone="$cash ? 'teal' : null" />
        <div class="nb-case__who">
            <strong>{{ $case->name }}</strong>
            <span>{{ $case->age ? $case->age.' سنة' : 'السن مش متسجل' }}@if ($case->governorate) · {{ $case->governorate }}@endif</span>
            <div class="nb-row">
                <x-nb.badge :tone="$cash ? 'teal' : 'gold'">{{ $case->benefit_type->label() }}</x-nb.badge>
                <x-nb.badge>{{ $case->benefit_period->label() }}</x-nb.badge>
            </div>
        </div>
    </div>
    <dl class="nb-case__facts">
        <div><dt>الأسرة</dt><dd>{{ $case->sons }} ولاد · {{ $case->daughters }} بنات</dd></div>
        <div><dt>الدخل</dt><dd class="nb-num">{{ number_format($case->totalIncome()) }} ج.م</dd></div>
        <div><dt>الحالة</dt><dd>{{ $case->marital_status?->label() ?? '—' }}</dd></div>
    </dl>
    <div class="nb-case__health">
        <i data-feather="heart"></i><span>{{ $case->health_status ?: 'الحالة الصحية مش متسجلة' }}</span>
        @if ($missing)
            <x-nb.badge tone="terra" :title="'ناقص: '.implode('، ', $missing)">ناقص: {{ $missing[0] }}@if (count($missing) > 1) +{{ count($missing) - 1 }}@endif</x-nb.badge>
        @endif
    </div>
    <div class="nb-case__foot">
        <button type="button" data-case-file="{{ route('sponsorship.cases.show', $case) }}"><i data-feather="file-text"></i> الملف</button>
        <button type="button" data-case-edit='@json(\App\Modules\Sponsorship\Http\CaseJson::of($case))'><i data-feather="edit-2"></i> تعديل</button>
    </div>
</article>
