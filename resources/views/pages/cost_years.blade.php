@php($current = $years->sortByDesc('year')->first())
@extends('layouts.master')
@section('title', 'الإشتراك السنوي')
@section('content')
    <x-nb.page title="الإشتراك السنوي" subtitle="قيمة الاشتراك لكل سنة. السنة بتبدأ يوليو وبتتسمى بسنة نهايتها" :crumbs="['الأعضاء والمالية']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#year-add">إضافة سنة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-split">
        <x-nb.card title="كل السنين" icon="calendar" flush>
            <x-nb.table id="years-table" :order="[[0, 'desc']]">
                <thead>
                    <tr>
                        <th>السنة</th>
                        <th>الفترة</th>
                        <th class="is-end">قيمة الإشتراك</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($years as $year)
                        <tr>
                            <td>
                                <div class="nb-cell-main">
                                    <strong class="nb-num">{{ $year->year }}</strong>
                                    @if ($current && $year->id === $current->id)
                                        <x-nb.badge tone="green" dot>الحالية</x-nb.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="nb-muted">يوليو {{ $year->year - 1 }} ← يونيو {{ $year->year }}</td>
                            <td class="is-end nb-strong">{{ number_format($year->cost) }} <small class="nb-muted">ج.م</small></td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#year-edit-'.$year->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#year-delete-'.$year->id" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>

        <div class="nb-stack">
            @if ($current)
                <x-nb.stat label="اشتراك السنة الحالية" :value="number_format($current->cost)" unit="ج.م" icon="calendar" main
                           :note="'سنة '.$current->year.' · يوليو '.($current->year - 1).' ← يونيو '.$current->year" />
            @endif
            <x-nb.card title="إزاي بتشتغل" icon="info" tone="gold">
                <ul style="margin: 0; padding-inline-start: 18px; line-height: 2" class="nb-muted">
                    <li>ضيف السنة الجديدة قبل يوليو بقيمتها.</li>
                    <li>بعدها ولّد المستحقات من «عمليات بالجملة» في صفحة المشتركين.</li>
                    <li>المستحق بيتسمى برقم السنة، زي 2027.</li>
                </ul>
            </x-nb.card>
        </div>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="year-add" title="إضافة سنة جديدة" icon="plus" size="sm" :action="route('costyears.store')" submit="إضافة">
        <div class="nb-form-grid">
            <x-nb.field label="السنة" name="year" type="number" min="2000" max="2100" :placeholder="(string) (($current->year ?? now()->year) + 1)" required />
            <x-nb.field label="المبلغ" name="cost" type="number" min="0" required />
        </div>
    </x-nb.modal>
    @foreach ($years as $year)
        <x-nb.modal :id="'year-edit-'.$year->id" :title="'تعديل سنة '.$year->year" icon="edit-2" size="sm" :action="route('costyears.update')">
            <input type="hidden" name="id" value="{{ $year->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="السنة" name="year" type="number" :value="$year->year" :row="$year->id" required />
                <x-nb.field label="المبلغ" name="cost" type="number" :value="$year->cost" :row="$year->id" required />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'year-delete-'.$year->id" :action="route('costyears.remove', $year->id)" :message="'هتحذف سنة '.$year->year.'؟'" />
    @endforeach
@endpush
