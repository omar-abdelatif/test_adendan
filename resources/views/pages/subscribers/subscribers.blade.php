@php
    $user = Auth::user();
    $count = fn ($status) => (int) ($statusCounts[$status] ?? 0);
    $total = $statusCounts->sum();
    $donationKinds = ['تبرع تنمية', 'تبرع إنتساب', 'مقابر قديمة', 'ص.مقر', 'ص.سيارة'];
    // Operations that touch many members at once; they open from the side drawer, grouped.
    $bulk = collect([
        ['can' => $user->can('مديونية الاشتراك'), 'group' => 'dues', 'modal' => '#bulk-year-dues', 'icon' => 'calendar', 'label' => 'إضافة إشتراك سنة على الأعضاء', 'text' => 'مستحق السنة اللي تختارها، بقيمة الاشتراك المسجلة، على كل عضو عليه اشتراك'],
        ['can' => $user->can('مديونية التبرعات'), 'group' => 'dues', 'modal' => '#bulk-donation-dues', 'icon' => 'heart', 'label' => 'مديونية تبرعات على كل المشتركين', 'text' => 'مبلغ واحد (تنمية، مقابر، مقر، سيارة…) بيتضاف مديونية على كل المشتركين'],
        ['can' => $user->hasRole('master'), 'group' => 'excel', 'modal' => '#bulk-members', 'icon' => 'upload', 'label' => 'إضافة مشتركين من إكسل', 'text' => 'ملف إكسل بنفس أعمدة فورم المشترك الجديد'],
        ['can' => $user->hasRole('master'), 'group' => 'excel', 'modal' => '#bulk-dues-file', 'icon' => 'file-plus', 'label' => 'إضافة مستحقات من إكسل', 'text' => 'مستحقات لكل عضو برقم عضويته'],
        ['can' => $user->hasRole('master'), 'group' => 'excel', 'modal' => '#bulk-transactions', 'icon' => 'repeat', 'label' => 'توليد معاملات دفع من إكسل', 'text' => 'لتسجيل مدفوعات قديمة مرة واحدة'],
        ['can' => $user->hasRole('master'), 'group' => 'app', 'modal' => '#generate-passwords', 'icon' => 'lock', 'label' => 'كلمات سر للتطبيق', 'text' => 'لكل عضو لسه معندوش كلمة سر، وبتوصله في رسالة SMS', 'danger' => true],
    ])->where('can', true);
    $bulkGroups = ['dues' => 'مديونيات على كل الأعضاء', 'excel' => 'استيراد من ملف إكسل', 'app' => 'حسابات التطبيق'];
@endphp
@extends('layouts.master')
@section('title', 'المشتركين')
@section('content')
    <x-nb.page title="كل المشتركين" :subtitle="'دوس على أي عضو تشوف ملفه جنب القائمة · سنة الاشتراك '.$year" :crumbs="['الأعضاء والمالية']">
        <x-slot:actions>
            @if ($bulk->isNotEmpty())
                <x-nb.button variant="ghost" icon="layers" open="#bulk-drawer" aria-haspopup="dialog">العمليات بالجملة</x-nb.button>
            @endif
            @can('مشترك جديد')
                <x-nb.button icon="user-plus" open="#member-add">إضافة مشترك جديد</x-nb.button>
            @endcan
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-browse">
        <section class="nb-card nb-browse__list" aria-label="قائمة الأعضاء">
            <div class="nb-browse__tools">
                <label class="nb-search">
                    <i data-feather="search"></i>
                    <span class="nb-sr">دوّر على عضو</span>
                    <input type="search" id="member-search" placeholder="اسم، رقم عضوية، أو موبايل" autocomplete="off">
                </label>
                <div class="nb-chips" id="member-status-chips" role="group" aria-label="حالة الاشتراك">
                    <a href="#" class="nb-chip is-on" data-status="" aria-pressed="true">الكل <em>{{ number_format($total) }}</em></a>
                    <a href="#" class="nb-chip" data-status="1" aria-pressed="false">مفعل <em>{{ number_format($count(1)) }}</em></a>
                    <a href="#" class="nb-chip" data-status="0" aria-pressed="false">غير مفعل <em>{{ number_format($count(0)) }}</em></a>
                    <a href="#" class="nb-chip" data-status="3" aria-pressed="false">معلق <em>{{ number_format($count(3)) }}</em></a>
                    <a href="#" class="nb-chip" data-status="2" aria-pressed="false">متوفي <em>{{ number_format($count(2)) }}</em></a>
                </div>
            </div>
            <table id="members-list" class="nb-browse__table" data-url="{{ route('ajax.subscribers') }}"
                   data-preview-url="{{ route('subscriber.preview', '__ID__') }}"></table>
            <footer class="nb-browse__foot">
                <span id="member-count" aria-live="polite"></span>
                <span class="nb-browse__pager">
                    <button type="button" class="nb-icon-btn" id="member-prev" aria-label="الصفحة اللي قبل كده" disabled><i data-feather="chevron-right"></i></button>
                    <button type="button" class="nb-icon-btn" id="member-next" aria-label="الصفحة اللي بعد كده" disabled><i data-feather="chevron-left"></i></button>
                </span>
            </footer>
            <p class="nb-browse__hint"><kbd>↑</kbd> <kbd>↓</kbd> للتنقل · <kbd>Enter</kbd> يفتح الملف</p>
        </section>

        <div id="member-preview" class="nb-browse__preview" aria-live="polite">
            <x-nb.card>
                <x-nb.empty icon="user" title="اختار عضو من القائمة" text="ملفه هيظهر هنا من غير ما تسيب الصفحة." />
            </x-nb.card>
        </div>
    </div>
@endsection

@push('modals')
    @if ($bulk->isNotEmpty())
        {{-- Side drawer: every bulk operation with what it does; "ابدأ" opens that operation's own form. --}}
        <x-nb.modal id="bulk-drawer" title="العمليات بالجملة" icon="layers" class="nb-modal--drawer">
            <div class="nb-stack-sm">
                <p class="nb-muted nb-drawer__intro">كل عملية هنا بتأثر على أعضاء كتير مرة واحدة. اقرا هي هتعمل إيه قبل ما تبدأ.</p>
                @foreach ($bulkGroups as $group => $groupTitle)
                    @php($items = $bulk->where('group', $group))
                    @continue($items->isEmpty())
                    <h3 @class(['nb-ops__title', 'nb-ops__title--danger' => $group === 'app'])>{{ $groupTitle }}</h3>
                    @foreach ($items as $item)
                        <div @class(['nb-op', 'nb-op--danger' => $item['danger'] ?? false])>
                            <span class="nb-op__icon" aria-hidden="true"><i data-feather="{{ $item['icon'] }}"></i></span>
                            <span class="nb-op__text"><strong>{{ $item['label'] }}</strong><span>{{ $item['text'] }}</span></span>
                            <button type="button" @class(['nb-btn', 'nb-btn--sm', ($item['danger'] ?? false) ? 'nb-btn--danger' : 'nb-btn--soft']) data-nb-start="{{ $item['modal'] }}">ابدأ</button>
                        </div>
                    @endforeach
                @endforeach
            </div>
            <x-slot:footer>
                <span class="nb-muted nb-drawer__note"><i data-feather="info"></i>كل عملية بتفتح الفورم بتاعها الأول، ومفيش حاجة بتتنفذ قبل ما تأكد.</span>
                <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>قفل</button>
            </x-slot:footer>
        </x-nb.modal>
    @endif

    {{-- donation from a member: the row button fills the member --}}
    <x-nb.modal id="member-donate" title="تبرع من عضو" icon="gift" :action="route('donations.store')" submit="تسجيل التبرع">
        <div class="nb-form-grid">
            <x-nb.field label="العضو" name="member_name_display" readonly disabled id="donate-member-name" span="full" />
            <x-nb.field label="رقم العضوية" name="member_id" type="number" readonly id="donate-member-id" required />
            <x-nb.field label="رقم الإيصال" name="invoice_no" type="number" inputmode="numeric" />
            <x-nb.choice label="نوع التبرع" name="donation_type" :options="['مادي' => 'مادي', 'أخرى' => 'أخرى']" required span="full" />
            <div class="nb-span-full" data-nb-when="donation_type:مادي"><x-nb.field label="بند التبرع" name="donation_category" type="select" :options="$donationKinds" required /></div>
            <div class="nb-span-full" data-nb-when="donation_type:أخرى"><x-nb.field label="نوع التبرع الأخر" name="other_donation" required /></div>
            <x-nb.field label="المبلغ" name="amount" type="number" min="1" required span="full" />
        </div>
    </x-nb.modal>

    @can('مشترك جديد')
        <x-nb.modal id="member-add" title="إضافة مشترك جديد" icon="user-plus" size="xl" :action="route('subscribe.store')" files submit="حفظ المشترك">
            <div class="nb-stack">
                <div class="nb-alert nb-alert--gold">
                    <i data-feather="info"></i>
                    <span>اشتراك سنة {{ $year }} = {{ number_format($cost) }} ج.م · العضو الجديد بيدفع {{ number_format($currentSubCost + 50) }} ج.م عن باقي السنة.</span>
                </div>
                <div class="nb-form-grid" style="--cols: 3">
                    <x-nb.section icon="user">البيانات الشخصية</x-nb.section>
                    <x-nb.field label="الإسم الكامل" name="name" required placeholder="بالعربي، 3 حروف على الأقل" />
                    <x-nb.field label="اللقب واسم الشهرة" name="nickname" />
                    <x-nb.field label="تاريخ الميلاد" name="birthdate" type="date" required />
                    <x-nb.field label="الرقم القومي" name="ssn" inputmode="numeric" maxlength="14" dir="ltr" required />
                    <x-nb.field label="رقم المحمول" name="mobile_no" type="tel" inputmode="numeric" maxlength="11" dir="ltr" required />
                    <x-nb.field label="رقم المحمول الاخر" name="another_mobile_no" type="tel" inputmode="numeric" maxlength="11" dir="ltr" />
                    <x-nb.field label="العنوان" name="address" required span="2" />
                    <x-nb.field label="الحالة الإجتماعية" name="martial_status" type="select" :options="['أعزب', 'متزوج', 'أرمل']" />
                    <x-nb.field label="تليفون المنزل" name="home_tel" type="tel" dir="ltr" />
                    <x-nb.field label="الصورة الشخصية" name="img" type="file" accept="image/*" />
                    <x-nb.field label="صورة البطاقة" name="id_img" type="file" accept="image/*" />

                    <x-nb.section icon="book">البيانات الدراسية</x-nb.section>
                    <x-nb.field label="المؤهل الدراسي" name="educational_qualification" />
                    <x-nb.field label="تاريخ المؤهل" name="qualification_date" type="date" />
                    <span></span>

                    <x-nb.section icon="briefcase">البيانات الوظيفية</x-nb.section>
                    <x-nb.field label="الوظيفة" name="job" />
                    <x-nb.field label="جهة العمل" name="job_destination" />
                    <x-nb.field label="عنوان العمل" name="job_address" />
                    <x-nb.field label="تليفون العمل" name="job_tel" type="tel" dir="ltr" />

                    <x-nb.section icon="credit-card">بيانات الإشتراك</x-nb.section>
                    <x-nb.choice label="نوع العضوية" name="membership_type" :options="['عامل', 'إنتساب']" required />
                    <x-nb.field label="رقم العضوية" name="member_id" type="number" :value="$newMemberId ?? 1" readonly hint="بيتولد تلقائي" />
                    <x-nb.field label="رقم إيصال الدفع" name="inv_no" inputmode="numeric" maxlength="5" />
                </div>
            </div>
        </x-nb.modal>
    @endcan

    @can('مديونية الاشتراك')
        <x-nb.modal id="bulk-year-dues" title="إضافة إشتراك سنة على الأعضاء" icon="calendar" :action="route('delays.costbyyear')" submit="إضافة المستحقات">
            <div class="nb-stack-sm">
                <x-nb.field label="السنة" name="item" type="select" :options="$years->pluck('year', 'year')->all()" required id="bulk-year" data-cost-url="{{ url('admin/subscribers/get-year-cost') }}" />
                <x-nb.field label="المبلغ" name="amount" type="number" readonly required id="bulk-year-cost" hint="بيتملى من قيمة الاشتراك المسجلة للسنة" />
                <div class="nb-alert nb-alert--gold"><i data-feather="alert-triangle"></i><span>هيتضاف مستحق للسنة دي على كل الأعضاء اللي عليهم اشتراك.</span></div>
            </div>
        </x-nb.modal>
    @endcan

    @can('مديونية التبرعات')
        <x-nb.modal id="bulk-donation-dues" title="مديونية تبرعات على كل المشتركين" icon="heart" :action="route('delays.uploadDonations')" submit="إضافة المديونية">
            <div class="nb-form-grid">
                <x-nb.field label="سبب المديونية" name="donation_type" type="select" :options="[...$donationKinds, 'أخرى']" required span="full" />
                <div class="nb-span-full" data-nb-when="donation_type:أخرى"><x-nb.field label="نوع التبرع الأخر" name="delay_other_amount" required /></div>
                <x-nb.field label="مبلغ المديونية" name="delay_amount" type="number" min="1" required span="full" />
            </div>
        </x-nb.modal>
    @endcan

    @if ($user->hasRole('master'))
        <x-nb.modal id="bulk-members" title="إضافة مشتركين بالجملة" icon="upload" :action="route('subscriber.bulk')" files submit="رفع الملف">
            <x-nb.field label="ملف الإكسل" name="import" type="file" accept=".xlsx,.xls" required hint="نفس أعمدة نموذج المشترك" />
        </x-nb.modal>
        <x-nb.modal id="bulk-dues-file" title="إضافة مستحقات بالجملة" icon="file-plus" :action="route('bulk_subscriber_dues')" files submit="رفع الملف">
            <x-nb.field label="ملف الإكسل" name="import-dues" type="file" accept=".xlsx,.xls" required />
        </x-nb.modal>
        <x-nb.modal id="bulk-transactions" title="توليد معاملات دفع بالإكسل" icon="repeat" :action="route('payment_transactions.import')" files submit="رفع الملف">
            <x-nb.field label="ملف الإكسل" name="import-transactions" type="file" accept=".xlsx,.xls" required hint="لتسجيل مدفوعات قديمة مرة واحدة" />
        </x-nb.modal>
        <x-nb.modal id="generate-passwords" title="كلمات سر للتطبيق" icon="lock" size="sm" danger>
            <div class="nb-confirm">
                <span class="nb-confirm__icon" aria-hidden="true"><i data-feather="alert-triangle"></i></span>
                <strong>هتتعمل كلمة سر لكل عضو لسه معندوش، وهتوصله في رسالة SMS.</strong>
                <span class="nb-muted">الأعضاء اللي عندهم كلمة سر مش هيتأثروا.</span>
            </div>
            <x-slot:footer>
                <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>إلغاء</button>
                <button type="button" class="nb-btn nb-btn--danger" id="generate-passwords-go" data-url="{{ route('generate-passwords') }}">أيوه، ولّد كلمات السر</button>
            </x-slot:footer>
        </x-nb.modal>
    @endif
@endpush

@push('scripts')
    <script>
        $(function () {
            // The list: server-side DataTables, each row arrives rendered by list-row.blade.php.
            const $t = $('#members-list');
            const $panel = $('#member-preview');
            const previewUrl = (id) => $t.data('previewUrl').replace('__ID__', encodeURIComponent(id));
            const stacked = window.matchMedia('(max-width: 1100px)');
            let current = (location.hash.match(/^#member-(\d+)$/) || [])[1] || null;
            let request = null;
            let firstDraw = true;

            const table = $t.DataTable({
                ajax: { url: $t.data('url') },
                processing: true,
                serverSide: true,
                dom: 'rt',
                pageLength: 15,
                order: [[1, 'asc']],
                columns: [
                    { data: 'row', title: 'العضو', orderable: false, searchable: false },
                    { data: 'member_id', visible: false },
                    { data: 'name', visible: false },
                    { data: 'mobile_no', visible: false },
                    { data: 'address', visible: false },
                    { data: 'status', visible: false, searchable: true },
                ],
                language: { zeroRecords: 'مفيش عضو بالبحث ده', emptyTable: 'مفيش أعضاء لسه' },
                drawCallback() {
                    window.nbIcons();
                    const info = table.page.info();
                    $('#member-count').text(info.recordsDisplay ? `${(info.start + 1).toLocaleString('ar-EG')} – ${info.end.toLocaleString('ar-EG')} من ${info.recordsDisplay.toLocaleString('ar-EG')}` : '');
                    $('#member-prev').prop('disabled', info.page <= 0);
                    $('#member-next').prop('disabled', info.page >= info.pages - 1);
                    mark();
                    if (firstDraw) {
                        firstDraw = false;
                        const first = $t.find('.nb-mrow').first();
                        if (current) show(current, previewUrl(current), false);
                        else if (first.length) show(first.data('id'), first.data('preview'), false);
                    }
                },
            });

            function mark() {
                $t.find('.nb-mrow').each(function () {
                    const on = String($(this).data('id')) === String(current);
                    $(this).toggleClass('is-on', on).attr('aria-current', on ? 'true' : null);
                });
            }

            // Loads a member's file into the panel beside the list.
            function show(id, url, scroll = true) {
                current = String(id);
                mark();
                history.replaceState(null, '', '#member-' + current);
                if (request) request.abort();
                $panel.addClass('is-loading').attr('aria-busy', 'true');
                request = $.get(url)
                    .done((html) => {
                        $panel.html(html);
                        window.nbIcons();
                        if (scroll && stacked.matches) $panel[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    })
                    .fail((x) => {
                        if (x.statusText === 'abort') return;
                        nbToast(x.status === 404 ? 'العضو ده مش موجود' : 'مقدرناش نفتح ملف العضو', 'error');
                    })
                    .always(() => $panel.removeClass('is-loading').attr('aria-busy', 'false'));
            }

            $t.on('click', '.nb-mrow', function () { show($(this).data('id'), $(this).data('preview')); });

            // ↑ ↓ move through the list, Enter opens the full file.
            $t.on('keydown', '.nb-mrow', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); location.href = $(this).data('open'); return; }
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                e.preventDefault();
                const rows = $t.find('.nb-mrow');
                const next = rows.eq(rows.index(this) + (e.key === 'ArrowDown' ? 1 : -1));
                if (!next.length) return;
                next.trigger('focus');
                show(next.data('id'), next.data('preview'), false);
            });

            let typing = null;
            $('#member-search').on('input', function () {
                clearTimeout(typing);
                typing = setTimeout(() => table.search(this.value.trim()).draw(), 300);
            });

            $('#member-status-chips').on('click', '.nb-chip', function (e) {
                e.preventDefault();
                $(this).addClass('is-on').attr('aria-pressed', 'true').siblings().removeClass('is-on').attr('aria-pressed', 'false');
                table.column(5).search(String($(this).data('status'))).draw();
            });

            // "ابدأ" in the bulk drawer: close the drawer, then open that operation's form.
            $(document).on('click', '[data-nb-start]', function () {
                const target = $(this).data('nbStart');
                nbModal.close('#bulk-drawer');
                nbModal.open(target);
            });

            $('#member-prev').on('click', () => table.page('previous').draw('page'));
            $('#member-next').on('click', () => table.page('next').draw('page'));

            // donation button in a row fills the shared modal
            $(document).on('click', '[data-nb-open="#member-donate"]', function () {
                $('#donate-member-id').val($(this).data('memberId'));
                $('#donate-member-name').val($(this).data('memberName'));
            });

            // yearly cost follows the chosen year
            $('#bulk-year').on('change', function () {
                const year = this.value;
                if (!year) return;
                $.getJSON($(this).data('costUrl') + '/' + encodeURIComponent(year))
                    .done((data) => $('#bulk-year-cost').val(data.cost ?? 0))
                    .fail(() => nbToast('مقدرناش نجيب قيمة الاشتراك للسنة دي', 'error'));
            });

            // new passwords for every member
            $('#generate-passwords-go').on('click', function () {
                const $b = $(this).prop('disabled', true).text('جاري التوليد…');
                $.post($b.data('url'))
                    .done((r) => { nbModal.close('#generate-passwords'); nbToast(`اتولّد ${r.count} كلمة سر جديدة`, 'success'); })
                    .fail((x) => nbToast(x.responseJSON?.message || 'مقدرناش نكمّل العملية', 'error'))
                    .always(() => $b.prop('disabled', false).text('أيوه، ولّد كلمات السر'));
            });
        });
    </script>
@endpush
