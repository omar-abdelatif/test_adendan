@php($phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—')
@extends('layouts.master')
@section('title', 'المتبرعين')
@section('content')
    <x-nb.page title="المتبرعين" subtitle="المتبرعين من بره العضوية، وتبرعاتهم الخارجية" :crumbs="['الأعضاء والمالية']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="bar-chart-2" :href="route('reports.donations')">تقرير التبرعات الخارجية</x-nb.button>
            <x-nb.button icon="plus" open="#donor-add">إضافة متبرع</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="المتبرعين" :value="$allDonators->count()" icon="gift" />
            <x-nb.stat label="منتظم" :value="$allDonators->where('donator_type', 'منتظم')->count()" icon="repeat" tone="green" />
            <x-nb.stat label="موسمي" :value="$allDonators->where('donator_type', 'موسمي')->count()" icon="sun" tone="gold" />
        </x-nb.stats>

        <x-nb.card title="كل المتبرعين" icon="gift" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#donors-table" data-col="2" data-value="">الكل</a>
                    <a href="#" class="nb-chip" data-nb-filter="#donors-table" data-col="2" data-value="منتظم">منتظم</a>
                    <a href="#" class="nb-chip" data-nb-filter="#donors-table" data-col="2" data-value="موسمي">موسمي</a>
                </div>
            </x-slot:actions>
            <x-nb.table id="donors-table" :order="[[0, 'asc']]">
                <thead>
                    <tr>
                        <th>المتبرع</th>
                        <th>رقم المحمول</th>
                        <th>النوع</th>
                        <th>مدة التبرع</th>
                        <th>تاريخ التسجيل</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allDonators as $donator)
                        <tr>
                            <td><div class="nb-cell-main"><x-nb.avatar :name="$donator->name" size="sm" /><strong>{{ $donator->name }}</strong></div></td>
                            <td class="nb-num" dir="ltr">{{ $phone($donator->mobile_number) }}</td>
                            <td><x-nb.badge :tone="$donator->donator_type === 'منتظم' ? 'green' : 'gold'">{{ $donator->donator_type }}</x-nb.badge></td>
                            <td>{{ $donator->duration ?: '—' }}</td>
                            <td class="nb-num nb-muted">{{ $donator->created_at?->format('Y-m-d') }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="plus-circle" label="تبرع جديد" tone="gold" :open="'#donor-give-'.$donator->id" />
                                    <x-nb.icon-button icon="clock" label="التبرعات السابقة" :href="route('outer_donations.history', $donator->id)" />
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#donor-edit-'.$donator->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#donor-delete-'.$donator->id" />
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
    <x-nb.modal id="donor-add" title="إضافة متبرع جديد" icon="plus" :action="route('donators.store')" submit="إضافة المتبرع">
        <div class="nb-form-grid">
            <x-nb.field label="إسم المتبرع" name="name" required />
            <x-nb.field label="رقم المحمول" name="mobile_number" type="tel" inputmode="numeric" dir="ltr" required />
            <x-nb.choice label="نوع المتبرع" name="donator_type" :options="['منتظم', 'موسمي']" required />
            <x-nb.field label="مدة التبرع" name="duration" placeholder="مثلاً: شهري، رمضان" />
        </div>
    </x-nb.modal>

    @foreach ($allDonators as $donator)
        <x-nb.modal :id="'donor-give-'.$donator->id" :title="'تبرع جديد من '.$donator->name" icon="plus-circle" :action="route('outer_donations.store')" submit="تسجيل التبرع">
            <input type="hidden" name="id" value="{{ $donator->id }}">
            <input type="hidden" name="name" value="{{ $donator->name }}">
            <input type="hidden" name="donator_type" value="{{ $donator->donator_type }}">
            <div class="nb-form-grid">
                <x-nb.field label="رقم الإيصال" name="invoice_id" type="number" :row="$donator->id" required />
                <x-nb.field label="المبلغ" name="amount" type="number" min="1" :row="$donator->id" required />
                <x-nb.field label="جهة التبرع" name="donation_destination" :row="$donator->id" required placeholder="مثلاً: صندوق المقر، كسوة الشتاء" span="full" />
                <x-nb.choice label="طريقة الدفع" name="payment_method" :options="['كاش', 'كارت', 'محفظة']" value="كاش" required span="full" />
            </div>
        </x-nb.modal>
        <x-nb.modal :id="'donor-edit-'.$donator->id" :title="'تعديل '.$donator->name" icon="edit-2" :action="route('donators.update')">
            <input type="hidden" name="id" value="{{ $donator->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم المتبرع" name="name" :value="$donator->name" :row="$donator->id" required />
                <x-nb.field label="رقم المحمول" name="mobile_number" type="tel" inputmode="numeric" dir="ltr" :value="$phone($donator->mobile_number)" :row="$donator->id" required />
                <x-nb.choice label="نوع المتبرع" name="donator_type" :options="['منتظم', 'موسمي']" :value="$donator->donator_type" required />
                <x-nb.field label="مدة التبرع" name="duration" :value="$donator->duration" :row="$donator->id" />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'donor-delete-'.$donator->id" :action="route('donators.remove', $donator->id)" :message="'هتحذف المتبرع '.$donator->name.'؟'" />
    @endforeach
@endpush
