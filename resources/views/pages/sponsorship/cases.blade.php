@php
    use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
    use App\Modules\Sponsorship\Domain\Enums\BenefitType;
    // A link to this page with some filters changed (and back to the first page).
    $url = fn (array $change) => route('sponsorship.cases.index', array_filter(array_merge($filters, $change), fn ($v) => $v !== null && $v !== ''));
    $active = fn (string $key, ?string $value) => ($filters[$key] ?? null) === $value || (($filters[$key] ?? null) === null && $value === null);
@endphp
@extends('layouts.master')
@section('title', 'الحالات')
@section('content')
    <x-nb.page title="الحالات" subtitle="كل حالة كارت فيه أهم حاجة: نوع الاستفادة، الأسرة، والدخل" :crumbs="['الكفالة']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="bar-chart-2" :href="route('sponsorship.reports')">تقرير الكفالة</x-nb.button>
            <x-nb.button variant="ghost" icon="upload" open="#cases-import">رفع حالات من إكسل</x-nb.button>
            <x-nb.button icon="plus" data-case-add>إضافة حالة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack" data-open-case="{{ session('open_case') }}">
        <section class="nb-card nb-filterbar">
            <form method="GET" action="{{ route('sponsorship.cases.index') }}" class="nb-search" role="search">
                <i data-feather="search"></i>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم، رقم قومي، أو موبايل" aria-label="بحث في الحالات">
                @foreach (['type', 'period', 'gov', 'sort'] as $keep)
                    @if (! empty($filters[$keep]))
                        <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
                    @endif
                @endforeach
            </form>
            <div class="nb-chips" aria-label="نوع الاستفادة">
                <a href="{{ $url(['type' => null, 'page' => null]) }}" @class(['nb-chip', 'is-on' => $active('type', null)])>الكل <em>{{ $counts['all'] }}</em></a>
                @foreach (BenefitType::cases() as $type)
                    <a href="{{ $url(['type' => $type->value, 'page' => null]) }}" @class(['nb-chip', 'is-on' => $active('type', $type->value)])>{{ $type->label() }} <em>{{ $counts['type'][$type->value] ?? 0 }}</em></a>
                @endforeach
            </div>
            <div class="nb-chips" aria-label="مدة الاستفادة">
                <a href="{{ $url(['period' => null, 'page' => null]) }}" @class(['nb-chip', 'is-on' => $active('period', null)])>أي مدة</a>
                @foreach (BenefitPeriod::cases() as $period)
                    <a href="{{ $url(['period' => $period->value, 'page' => null]) }}" @class(['nb-chip', 'is-on' => $active('period', $period->value)])>{{ $period->label() }} <em>{{ $counts['period'][$period->value] ?? 0 }}</em></a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('sponsorship.cases.index') }}" class="nb-row" data-case-filters>
                @foreach (['q', 'type', 'period'] as $keep)
                    @if (! empty($filters[$keep]))
                        <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
                    @endif
                @endforeach
                <select name="gov" class="nb-input" aria-label="المحافظة">
                    <option value="">كل المحافظات</option>
                    @foreach ($governorates as $gov)
                        <option value="{{ $gov }}" @selected(($filters['gov'] ?? '') === $gov)>{{ $gov }}</option>
                    @endforeach
                </select>
                <select name="sort" class="nb-input" aria-label="الترتيب">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['sort'] ?? 'income') === $key)>ترتيب: {{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </section>

        @if ($cases->isEmpty())
            <x-nb.card>
                @if ($counts['all'] === 0)
                    <x-nb.empty icon="users" title="مفيش حالات متسجلة لسه" text="ضيف أول حالة، أو ارفع شيت الحالات من النظام القديم.">
                        <x-nb.button icon="plus" data-case-add>إضافة حالة</x-nb.button>
                    </x-nb.empty>
                @else
                    <x-nb.empty icon="search" title="مفيش حالات بالفلاتر دي" text="غيّر البحث أو امسح الفلاتر.">
                        <x-nb.button variant="ghost" :href="route('sponsorship.cases.index')">مسح الفلاتر</x-nb.button>
                    </x-nb.empty>
                @endif
            </x-nb.card>
        @else
            <div class="nb-cases" data-case-grid>
                @foreach ($cases as $case)
                    @include('pages.sponsorship.partials.case-card', ['case' => $case])
                @endforeach
            </div>
            <div class="nb-more" data-case-more-wrap>
                <span class="nb-muted">عرض <span data-case-shown>{{ $cases->lastItem() }}</span> من {{ $cases->total() }}</span>
                @if ($cases->hasMorePages())
                    <x-nb.button variant="ghost" icon="chevron-down" :href="$cases->nextPageUrl()" data-case-more>هات {{ min(\App\Modules\Sponsorship\Application\Queries\CaseViews::PER_PAGE, $cases->total() - $cases->lastItem()) }} كمان</x-nb.button>
                @endif
            </div>
        @endif
    </div>
@endsection

@push('modals')
    @include('pages.sponsorship.partials.case-form', ['governorates' => $governorates])

    <x-nb.modal id="case-file" title="ملف الحالة" icon="file-text" class="nb-modal--drawer">
        <div data-case-file-body><p class="nb-muted">جاري التحميل…</p></div>
        <x-slot:footer>
            <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>قفل</button>
        </x-slot:footer>
    </x-nb.modal>

    <x-nb.confirm id="case-delete" :action="route('sponsorship.cases.store')" message="">
        <span class="nb-muted">المستندات اللي في الملف هتتحذف معاها.</span>
    </x-nb.confirm>

    <x-nb.modal id="cases-import" title="رفع حالات من إكسل" icon="upload" size="sm" :action="route('sponsorship.cases.import')" files submit="رفع">
        <div class="nb-stack-sm">
            <x-nb.field label="ملف الإكسل" name="sheet" type="file" accept=".xlsx,.xls" required />
            <p class="nb-muted" style="margin: 0; font-size: 13px">
                الأعمدة: name, national_id, phone, age, address, governorate, marital_status, health_status, sons, daughters,
                monthly_income, pension_income, other_income, benefit_type (نقدية أو عينية), benefit_period (شهرية أو موسمية).
                شيت النظام القديم بأعمدته (fullname, ssn, gov…) بيترفع زي ما هو. الحالة اللي رقمها القومي متسجل بتتساب.
            </p>
        </div>
    </x-nb.modal>
@endpush

@push('scripts')
    <script>
        (function () {
            const drawer = '#case-file';
            const body = () => document.querySelector('[data-case-file-body]');

            async function openFile(url) {
                body().innerHTML = '<p class="nb-muted">جاري التحميل…</p>';
                nbModal.open(drawer);
                try {
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) throw new Error(res.status);
                    body().innerHTML = await res.text();
                    window.nbIcons();
                } catch (err) {
                    body().innerHTML = '<div class="nb-alert nb-alert--terra">الملف ماتحملش، جرّب تاني.</div>';
                }
            }

            $(document).on('click', '[data-case-file]', function () { openFile(this.dataset.caseFile); });

            // Filters: the selects apply as soon as they change.
            $(document).on('change', '[data-case-filters] select', function () { this.form.requestSubmit(); });

            // "هات كمان": the next page's cards join this page.
            $(document).on('click', '[data-case-more]', async function (e) {
                e.preventDefault();
                const link = this;
                link.setAttribute('aria-busy', 'true');
                try {
                    const res = await fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    const grid = document.querySelector('[data-case-grid]');
                    doc.querySelectorAll('[data-case-grid] > *').forEach((card) => grid.append(card));
                    document.querySelector('[data-case-more-wrap]').replaceWith(doc.querySelector('[data-case-more-wrap]'));
                    window.nbIcons();
                } catch (err) {
                    location.assign(link.href);
                }
            });

            $(document).on('click', '[data-case-delete]', function () {
                const o = $(this).data('caseDelete');
                const m = document.getElementById('case-delete');
                m.querySelector('form').setAttribute('action', o.url);
                m.querySelector('.nb-confirm strong').textContent = `هتحذف حالة ${o.name}؟`;
                nbModal.close(drawer);
                nbModal.open('#case-delete');
            });

            // After adding papers or saving a case, its file opens again.
            const again = document.querySelector('[data-open-case]');
            if (again && again.dataset.openCase) openFile(@json(url('admin/sponsorship/cases')) + '/' + again.dataset.openCase);
        })();
    </script>
@endpush
