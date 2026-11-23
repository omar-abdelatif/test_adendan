@php($crafts = ['نجار', 'نقاش', 'سباك', 'كهربائي', 'فني', 'أخرى'])
@extends('layouts.master')
@section('title', 'الحرفيين')
@section('content')
    <x-nb.page title="الحرفيين" subtitle="دليل الحرفيين من أبناء أدندان، بيظهر للأعضاء في التطبيق" :crumbs="['الجمعية والدليل']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#worker-add">إضافة حرفي</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="الحرفيين" :value="$workers->count()" icon="briefcase" />
            <x-nb.stat label="الحرف" :value="$workers->pluck('craft')->unique()->count()" icon="settings" tone="gold" />
            <x-nb.stat label="المناطق" :value="$workers->pluck('location')->filter()->unique()->count()" icon="map-pin" tone="green" />
        </x-nb.stats>

        <x-nb.card title="كل الحرفيين" icon="briefcase" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#workers-table" data-col="1" data-value="">الكل <em>{{ $workers->count() }}</em></a>
                    @foreach ($crafts as $craft)
                        <a href="#" class="nb-chip" data-nb-filter="#workers-table" data-col="1" data-value="{{ $craft }}">{{ $craft }} <em>{{ $workers->where('craft', $craft)->count() }}</em></a>
                    @endforeach
                </div>
            </x-slot:actions>
            <x-nb.table id="workers-table" :order="[[0, 'asc']]">
                <thead>
                    <tr>
                        <th>الحرفي</th>
                        <th>الحرفة</th>
                        <th>تفاصيل الحرفة</th>
                        <th>المنطقة</th>
                        <th>رقم المحمول</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($workers as $worker)
                        <tr>
                            <td><div class="nb-cell-main"><x-nb.avatar :name="$worker->name" size="sm" /><strong>{{ $worker->name }}</strong></div></td>
                            <td><x-nb.badge tone="teal">{{ $worker->craft }}</x-nb.badge></td>
                            <td>{{ $worker->other_craft ?: '—' }}</td>
                            <td>{{ $worker->location ?: '—' }}</td>
                            <td class="nb-num" dir="ltr">0{{ ltrim((string) $worker->phone_number, '0') }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="phone" label="اتصال" :href="'tel:0'.ltrim((string) $worker->phone_number, '0')" />
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#worker-edit-'.$worker->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#worker-delete-'.$worker->id" />
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
    <x-nb.modal id="worker-add" title="إضافة حرفي جديد" icon="plus" :action="route('worker.store')" submit="إضافة">
        <div class="nb-form-grid">
            <x-nb.field label="إسم الحرفي" name="name" required />
            <x-nb.field label="رقم الموبايل" name="phone_number" type="tel" inputmode="numeric" dir="ltr" required />
            <x-nb.field label="الحرفة" name="craft" type="select" :options="$crafts" required />
            <div data-nb-when="craft:فني|أخرى"><x-nb.field label="إسم الحرفة الأخرى" name="other_craft" placeholder="مثلاً: تكييفات" /></div>
            <x-nb.field label="المنطقة" name="location" required span="full" />
        </div>
    </x-nb.modal>

    @foreach ($workers as $worker)
        <x-nb.modal :id="'worker-edit-'.$worker->id" :title="'تعديل '.$worker->name" icon="edit-2" :action="route('worker.update')">
            <input type="hidden" name="id" value="{{ $worker->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم الحرفي" name="name" :value="$worker->name" :row="$worker->id" required />
                <x-nb.field label="رقم الموبايل" name="phone_number" type="tel" inputmode="numeric" dir="ltr" :value="'0'.ltrim((string) $worker->phone_number, '0')" :row="$worker->id" required />
                <x-nb.field label="الحرفة" name="craft" type="select" :options="$crafts" :value="$worker->craft" :row="$worker->id" required />
                <div data-nb-when="craft:فني|أخرى"><x-nb.field label="إسم الحرفة الأخرى" name="other_craft" :value="$worker->other_craft" :row="$worker->id" /></div>
                <x-nb.field label="المنطقة" name="location" :value="$worker->location" :row="$worker->id" required span="full" />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'worker-delete-'.$worker->id" :action="route('worker.delete', $worker->id)" :message="'هتحذف '.$worker->name.' من الدليل؟'" />
    @endforeach
@endpush
