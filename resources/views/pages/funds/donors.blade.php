@php
    use App\Modules\Finance\Domain\Enums\Fund;
    use App\Modules\Finance\Http\Requests\DonorRequest;
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—';
    $crumbs = $fund === Fund::Sponsorship ? ['الكفالة' => route('sponsorship.cases.index')] : ['المقابر' => route('cemeteries.index')];
    $donorJson = fn ($d) => ['url' => route('funds.donors.update', [$fund, $d]), 'name' => $d->name, 'mobile_number' => $phone($d->mobile_number), 'donator_type' => $d->donator_type, 'duration' => $d->duration];
@endphp
@extends('layouts.master')
@section('title', 'متبرعين '.$fund->label())
@section('content')
    <x-nb.page :title="'متبرعين '.$fund->label()" :subtitle="'التبرعات بتدخل خزنة '.$fund->label().'، ومش بتتخلط بفلوس الجمعية. المتبرع واحد في كل الصناديق.'" :crumbs="$crumbs">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="calendar" :href="route('funds.donations', $fund)">تقرير التبرعات</x-nb.button>
            <x-nb.button variant="ghost" icon="user-plus" open="#donor-add">متبرع جديد</x-nb.button>
            <x-nb.button icon="plus-circle" :data-donation-open="json_encode(['mode' => 'add'])">تبرع جديد</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack" data-give-to="{{ session('give_to') }}">
        @if (session('receipt'))
            <div class="nb-alert nb-receipt-bar"><i data-feather="check-circle"></i><span>{{ session('message') }}</span>
                <a class="nb-btn nb-btn--sm" href="{{ session('receipt') }}" target="_blank" rel="noopener"><i data-feather="printer"></i>اطبع الإيصال</a>
            </div>
        @endif

        <x-nb.stats>
            <x-nb.stat label="تبرعات الشهر ده" :value="number_format($totals['month'])" unit="ج.م" icon="calendar" main />
            <x-nb.stat label="تبرعات السنة" :value="number_format($totals['year'])" unit="ج.م" icon="trending-up" tone="green" />
            <x-nb.stat label="اتبرعوا للصندوق" :value="number_format($totals['givers'])" icon="heart" tone="gold" :note="'من '.number_format($totals['donors']).' متبرع متسجل'" />
        </x-nb.stats>

        <x-nb.card title="المتبرعين" subtitle="اللي اتبرعوا للصندوق الأول، وبعدهم باقي المتبرعين المتسجلين" icon="gift" flush>
            <x-nb.table id="fund-donors" :page-length="25" :order="[[5, 'desc']]">
                <thead>
                    <tr>
                        <th>المتبرع</th>
                        <th>الموبايل</th>
                        <th>النوع</th>
                        <th>التبرعات</th>
                        <th>الإجمالي</th>
                        <th>آخر تبرع</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($donors as $donor)
                        <tr>
                            <td><div class="nb-cell-main"><x-nb.avatar :name="$donor->name" size="sm" /><strong>{{ $donor->name }}</strong></div></td>
                            <td class="nb-num" dir="ltr">{{ $phone($donor->mobile_number) }}</td>
                            <td><x-nb.badge :tone="$donor->donator_type === 'منتظم' ? 'green' : 'gold'">{{ $donor->donator_type }}</x-nb.badge></td>
                            <td class="nb-num" data-order="{{ $donor->fund_count }}">{{ $donor->fund_count ?: '—' }}</td>
                            <td class="nb-num" data-order="{{ (int) $donor->fund_total }}">{{ $donor->fund_total ? number_format($donor->fund_total).' ج.م' : '—' }}</td>
                            <td class="nb-num nb-muted" data-order="{{ $donor->fund_last ?? '0000' }}">{{ $donor->fund_last ? \Illuminate\Support\Carbon::parse($donor->fund_last)->format('Y-m-d') : '—' }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="plus-circle" label="تبرع جديد" tone="gold" :data-donation-open="json_encode(['mode' => 'add', 'donator_id' => $donor->id])" />
                                    <x-nb.icon-button icon="clock" label="سجل التبرعات" :data-donor-history="route('funds.donors.show', [$fund, $donor])" />
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :data-donor-edit="json_encode($donorJson($donor))" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection

@push('modals')
    @include('pages.funds.partials.donation-form', ['fund' => $fund, 'donors' => $donors, 'categories' => $categories, 'months' => $months])

    <x-nb.modal id="donor-add" title="متبرع جديد" icon="user-plus" :action="route('funds.donors.store', $fund)" submit="إضافة المتبرع" :autoopen="old('_form') === 'donor-add'">
        <input type="hidden" name="_form" value="donor-add">
        <div class="nb-form-grid">
            <x-nb.field label="اسم المتبرع" name="name" required />
            <x-nb.field label="رقم الموبايل" name="mobile_number" type="tel" inputmode="numeric" dir="ltr" required hint="لو الرقم متسجل، المتبرع موجود في القايمة" />
            <x-nb.choice label="نوع المتبرع" name="donator_type" :options="DonorRequest::TYPES" required />
            <x-nb.field label="مدة التبرع" name="duration" placeholder="مثلاً: شهري، رمضان" />
        </div>
    </x-nb.modal>

    <x-nb.modal id="donor-edit" title="تعديل المتبرع" icon="edit-2" :action="route('funds.donors.store', $fund)" method="PUT">
        <div class="nb-form-grid">
            <x-nb.field label="اسم المتبرع" name="name" required id="de-name" />
            <x-nb.field label="رقم الموبايل" name="mobile_number" type="tel" inputmode="numeric" dir="ltr" required id="de-mobile" />
            <x-nb.choice label="نوع المتبرع" name="donator_type" :options="DonorRequest::TYPES" required />
            <x-nb.field label="مدة التبرع" name="duration" id="de-duration" />
        </div>
    </x-nb.modal>

    <x-nb.modal id="donor-history" title="سجل التبرعات" icon="clock" class="nb-modal--drawer">
        <div data-donor-history-body><p class="nb-muted">جاري التحميل…</p></div>
        <x-slot:footer>
            <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>قفل</button>
        </x-slot:footer>
    </x-nb.modal>
@endpush

@push('scripts')
    <script>
        (function () {
            $(document).on('click', '[data-donor-history]', async function () {
                const body = document.querySelector('[data-donor-history-body]');
                body.innerHTML = '<p class="nb-muted">جاري التحميل…</p>';
                nbModal.open('#donor-history');
                try {
                    const res = await fetch(this.dataset.donorHistory, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) throw new Error(res.status);
                    body.innerHTML = await res.text();
                    window.nbIcons();
                } catch (err) {
                    body.innerHTML = '<div class="nb-alert nb-alert--terra">السجل ماتحملش، جرّب تاني.</div>';
                }
            });

            $(document).on('click', '[data-donor-edit]', function () {
                const o = $(this).data('donorEdit');
                const form = document.querySelector('#donor-edit form');
                form.setAttribute('action', o.url);
                form.querySelector('[name="name"]').value = o.name || '';
                form.querySelector('[name="mobile_number"]').value = o.mobile_number || '';
                form.querySelector('[name="duration"]').value = o.duration || '';
                form.querySelectorAll('[name="donator_type"]').forEach((r) => { r.checked = r.value === o.donator_type; });
                document.querySelector('#donor-edit .nb-modal__head h2').textContent = 'تعديل ' + o.name;
                nbModal.open('#donor-edit');
            });

            // A donor just added: their first donation form opens.
            const giveTo = document.querySelector('[data-give-to]');
            if (giveTo && giveTo.dataset.giveTo) {
                const opener = document.createElement('button');
                opener.dataset.donationOpen = JSON.stringify({ mode: 'add', donator_id: Number(giveTo.dataset.giveTo) });
                opener.hidden = true;
                document.body.append(opener);
                opener.click();
                opener.remove();
            }
        })();
    </script>
@endpush
