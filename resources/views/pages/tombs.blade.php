@php
    $regions = ['أكتوبر', 'الفيوم', '15مايو', 'القطامية', 'الغفير', 'زينهم', 'العين السخنة', 'القرية'];
    $kinds = ['عيون', 'لحد'];
    $types = ['رجال', 'سيدات', 'مختلط'];
@endphp
@extends('layouts.master')
@section('title', 'المقابر')
@section('content')
    <x-nb.page title="المقابر" subtitle="مقابر الجمعية، والتُربي المسؤول عن كل مقبرة" :crumbs="['الجمعية والدليل']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#tomb-add">إضافة مقبرة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="المقابر" :value="$tombs->count()" icon="moon" />
            <x-nb.stat label="المناطق" :value="$tombs->pluck('region')->unique()->count()" icon="map" tone="gold" />
            <x-nb.stat label="التُربية" :value="$tombs->pluck('tomb_guard_name')->filter()->unique()->count()" icon="user" tone="green" />
        </x-nb.stats>

        <x-nb.card title="كل المقابر" icon="moon" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#tombs-table" data-col="2" data-value="">الكل <em>{{ $tombs->count() }}</em></a>
                    @foreach ($types as $type)
                        <a href="#" class="nb-chip" data-nb-filter="#tombs-table" data-col="2" data-value="{{ $type }}">{{ $type }} <em>{{ $tombs->where('tomb_type', $type)->count() }}</em></a>
                    @endforeach
                </div>
            </x-slot:actions>
            <x-nb.table id="tombs-table" :order="[[1, 'asc']]">
                <thead>
                    <tr>
                        <th>إسم المقبرة</th>
                        <th>المنطقة</th>
                        <th>التصنيف</th>
                        <th>النوع</th>
                        <th>التُربي</th>
                        <th>رقم المحمول</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tombs as $tomb)
                        <tr>
                            <td>
                                <div class="nb-cell-main">
                                    <span class="nb-card__icon" aria-hidden="true"><i data-feather="moon"></i></span>
                                    <strong>{{ $tomb->title }}</strong>
                                </div>
                            </td>
                            <td><x-nb.badge tone="teal">{{ $tomb->region }}</x-nb.badge></td>
                            <td>{{ $tomb->tomb_type }}</td>
                            <td>{{ $tomb->tomb_specialization }}</td>
                            <td>{{ $tomb->tomb_guard_name ?: '—' }}</td>
                            <td class="nb-num">{{ $tomb->tomb_guard_number ?: '—' }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    @if ($tomb->location)
                                        <x-nb.icon-button icon="map-pin" label="الموقع على الخريطة" :href="$tomb->location" target="_blank" rel="noopener" />
                                    @endif
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#tomb-edit-'.$tomb->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#tomb-delete-'.$tomb->id" />
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
    <x-nb.modal id="tomb-add" title="إضافة مقبرة جديدة" icon="plus" :action="route('tomb.store')" submit="إضافة المقبرة">
        <div class="nb-form-grid">
            <x-nb.field label="إسم المقبرة" name="title" required span="full" />
            <x-nb.field label="المنطقة" name="region" type="select" :options="$regions" required />
            <x-nb.choice label="نوع المقبرة" name="tomb_specialization" :options="$kinds" required />
            <x-nb.choice label="تصنيف المقبرة" name="tomb_type" :options="$types" required span="full" />
            <x-nb.field label="إسم التربي" name="tomb_guard_name" />
            <x-nb.field label="رقم المحمول" name="tomb_guard_number" type="tel" inputmode="numeric" dir="ltr" />
            <x-nb.field label="الموقع (رابط خرائط جوجل)" name="location" type="url" dir="ltr" required span="full" placeholder="https://maps.google.com/..." />
        </div>
    </x-nb.modal>

    @foreach ($tombs as $tomb)
        <x-nb.modal :id="'tomb-edit-'.$tomb->id" :title="'تعديل '.$tomb->title" icon="edit-2" :action="route('tomb.update')">
            <input type="hidden" name="id" value="{{ $tomb->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم المقبرة" name="title" :value="$tomb->title" :row="$tomb->id" required span="full" />
                <x-nb.field label="المنطقة" name="region" type="select" :options="$regions" :value="$tomb->region" :row="$tomb->id" required />
                <x-nb.choice label="نوع المقبرة" name="tomb_specialization" :options="$kinds" :value="$tomb->tomb_specialization" required />
                <x-nb.choice label="تصنيف المقبرة" name="tomb_type" :options="$types" :value="$tomb->tomb_type" required span="full" />
                <x-nb.field label="إسم التربي" name="tomb_guard_name" :value="$tomb->tomb_guard_name" :row="$tomb->id" />
                <x-nb.field label="رقم المحمول" name="tomb_guard_number" type="tel" inputmode="numeric" dir="ltr" :value="$tomb->tomb_guard_number" :row="$tomb->id" />
                <x-nb.field label="الموقع (رابط خرائط جوجل)" name="location" type="url" dir="ltr" :value="$tomb->location" :row="$tomb->id" span="full" />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'tomb-delete-'.$tomb->id" :action="route('tomb.delete', $tomb->id)" :message="'هتحذف '.$tomb->title.'؟'" />
    @endforeach
@endpush
