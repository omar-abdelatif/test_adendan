{{-- The case file, loaded into the #case-file drawer on the cases page. --}}
@php
    use App\Modules\Sponsorship\Domain\Enums\BenefitType;
    use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
    $cash = $case->benefit_type === BenefitType::Cash;
    $missing = $case->missing();
    $show = fn ($v) => $v === null || $v === '' ? '—' : $v;
@endphp
<div class="nb-stack-sm">
    <div class="nb-casefile__head">
        <x-nb.avatar :name="$case->name" :tone="$cash ? 'teal' : null" />
        <div>
            <strong>{{ $case->name }}</strong>
            <span class="nb-muted" style="font-size: 12.5px">اتسجلت {{ $case->created_at?->locale('ar')->translatedFormat('j F Y') }}</span>
            <div class="nb-row">
                <x-nb.badge :tone="$cash ? 'teal' : 'gold'">{{ $case->benefit_type->label() }}</x-nb.badge>
                <x-nb.badge>{{ $case->benefit_period->label() }}</x-nb.badge>
            </div>
        </div>
    </div>

    @if ($missing)
        <div class="nb-alert nb-alert--terra"><i data-feather="alert-circle"></i><span>ناقص من الملف: {{ implode('، ', $missing) }}</span></div>
    @else
        <div class="nb-alert"><i data-feather="check-circle"></i><span>الملف كامل</span></div>
    @endif

    <section class="nb-card" style="padding: 14px 16px">
        <dl class="nb-kv">
            <dt>الرقم القومي</dt><dd class="nb-num" dir="ltr" style="text-align: end">{{ $show($case->national_id) }}</dd>
            <dt>الموبايل</dt><dd>@if ($case->phone)<a href="tel:{{ $case->phone }}" dir="ltr">{{ $case->phone }}</a>@else — @endif @if ($case->phone_alt) · <a href="tel:{{ $case->phone_alt }}" dir="ltr">{{ $case->phone_alt }}</a>@endif</dd>
            <dt>السن</dt><dd>{{ $case->age ? $case->age.' سنة' : '—' }}</dd>
            <dt>المحافظة</dt><dd>{{ $show($case->governorate) }}</dd>
            <dt>العنوان</dt><dd>{{ $show($case->address) }}</dd>
            <dt>الحالة الاجتماعية</dt><dd>{{ $case->marital_status?->label() ?? '—' }}</dd>
            <dt>الأسرة</dt><dd>{{ $case->sons }} ولاد · {{ $case->daughters }} بنات</dd>
            <dt>الحالة الصحية</dt><dd>{{ $show($case->health_status) }}</dd>
        </dl>
    </section>

    <h3 class="nb-ops__title">الدخل في الشهر</h3>
    <div class="nb-income">
        <div><span>الدخل الشهري</span><strong class="nb-num">{{ number_format($case->monthly_income) }}</strong></div>
        <div><span>المعاش</span><strong class="nb-num">{{ number_format($case->pension_income) }}</strong></div>
        <div><span>مصدر تاني</span><strong class="nb-num">{{ number_format($case->other_income) }}</strong></div>
        <div class="is-total"><span>الإجمالي</span><strong class="nb-num">{{ number_format($case->totalIncome()) }}</strong></div>
    </div>

    @if ($case->notes)
        <h3 class="nb-ops__title">ملاحظات</h3>
        <p style="margin: 0; white-space: pre-line">{{ $case->notes }}</p>
    @endif

    <h3 class="nb-ops__title">المستندات</h3>
    @forelse ($case->files as $file)
        <div class="nb-card nb-item">
            <span class="nb-item__icon" aria-hidden="true"><i data-feather="{{ $file->isImage() ? 'image' : 'file-text' }}"></i></span>
            <div class="nb-item__main">
                <strong>{{ $file->kind->label() }}</strong>
                <span>{{ $file->original_name }} · {{ $file->created_at?->format('Y-m-d') }}</span>
            </div>
            <span class="nb-item__acts">
                <a class="nb-icon-btn" href="{{ route('sponsorship.files.show', $file) }}" target="_blank" rel="noopener" title="فتح"><i data-feather="external-link"></i></a>
                <form method="POST" action="{{ route('sponsorship.files.destroy', $file) }}" data-nb-busy="جاري الحذف…">
                    @csrf @method('DELETE')
                    <button type="submit" class="nb-icon-btn nb-icon-btn--danger" title="حذف المستند"><i data-feather="trash-2"></i></button>
                </form>
            </span>
        </div>
    @empty
        <p class="nb-muted" style="margin: 0">مفيش مستندات في الملف لسه.</p>
    @endforelse

    <form method="POST" action="{{ route('sponsorship.cases.files.store', $case) }}" enctype="multipart/form-data" class="nb-card nb-stack-sm" style="padding: 14px 16px">
        @csrf
        <strong style="font-size: 14px">ضيف مستند</strong>
        <div class="nb-form-grid">
            <x-nb.field label="نوعه" name="kind" type="select" :options="CaseDocument::options()" :value="$missing && in_array(CaseDocument::IdCard->label(), $missing, true) ? CaseDocument::IdCard->value : CaseDocument::Photo->value" :empty="false" required :row="$case->id" />
            <x-nb.field label="الملف" name="files[]" type="file" accept=".pdf,image/*" multiple required :row="$case->id" />
        </div>
        <div><button type="submit" class="nb-btn nb-btn--sm"><i data-feather="upload"></i>رفع</button></div>
    </form>

    <div class="nb-row" style="margin-top: 6px">
        <button type="button" class="nb-btn" data-case-edit='@json(\App\Modules\Sponsorship\Http\CaseJson::of($case))'><i data-feather="edit-2"></i>تعديل البيانات</button>
        <button type="button" class="nb-btn nb-btn--ghost nb-out" data-case-delete='@json(["url" => route("sponsorship.cases.destroy", $case), "name" => $case->name])'><i data-feather="trash-2"></i>حذف الحالة</button>
    </div>
</div>
