@php
    $days = ['السبت', 'الأحد', 'الأثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
    $today = now()->toDateString();
    $upcoming = $weddings->filter(fn ($w) => (string) $w->date >= $today);
    $time = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('ar')->translatedFormat('g:i a') : '—';
@endphp
@extends('layouts.master')
@section('title', 'الأفراح')
@section('content')
    <x-nb.page title="الأفراح" subtitle="مواعيد أفراح أبناء أدندان اللي بتظهر في التطبيق" :crumbs="['التواصل']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="upload" open="#wedding-import">رفع ملف إكسل</x-nb.button>
            <x-nb.button icon="plus" open="#wedding-add">إضافة فرح</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="أفراح جاية" :value="$upcoming->count()" icon="heart" />
            <x-nb.stat label="خلال 7 أيام" :value="$upcoming->filter(fn ($w) => (string) $w->date <= now()->addDays(7)->toDateString())->count()" icon="calendar" tone="gold" />
            <x-nb.stat label="كل الأفراح المسجلة" :value="$weddings->count()" icon="list" tone="green" />
        </x-nb.stats>

        <x-nb.card title="كل الأفراح" icon="heart" flush>
            <x-nb.table id="weddings-table" :order="[[1, 'desc']]">
                <thead>
                    <tr>
                        <th>العريس</th>
                        <th>التاريخ</th>
                        <th>اليوم</th>
                        <th>والد العروس</th>
                        <th>المكان</th>
                        <th>الوقت</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weddings as $wedding)
                        <tr>
                            <td><div class="nb-cell-main"><span class="nb-card__icon" aria-hidden="true"><i data-feather="heart"></i></span><strong>{{ $wedding->groom_name }}</strong></div></td>
                            <td data-order="{{ $wedding->date }}" class="nb-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($wedding->date)->locale('ar')->translatedFormat('j F Y') }}
                                @if ((string) $wedding->date >= $today)
                                    <x-nb.badge tone="green">جاي</x-nb.badge>
                                @endif
                            </td>
                            <td>{{ $wedding->day }}</td>
                            <td>{{ $wedding->pride_father_name }}</td>
                            <td>{{ $wedding->address }}</td>
                            <td class="nb-nowrap">{{ $time($wedding->from_time) }} – {{ $time($wedding->to_time) }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#wedding-edit-'.$wedding->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#wedding-delete-'.$wedding->id" />
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
    <x-nb.modal id="wedding-add" title="إضافة فرح جديد" icon="plus" :action="route('weddings.store')" submit="إضافة الفرح">
        <div class="nb-form-grid">
            <x-nb.field label="إسم العريس" name="groom_name" required />
            <x-nb.field label="والد العروس" name="pride_father_name" required />
            <x-nb.field label="تاريخ المناسبة" name="date" type="date" required />
            <x-nb.field label="اليوم" name="day" type="select" :options="$days" required />
            <x-nb.field label="من الساعة" name="from_time" type="time" required />
            <x-nb.field label="الى الساعة" name="to_time" type="time" required />
            <x-nb.field label="العنوان" name="address" required span="full" />
        </div>
    </x-nb.modal>

    <x-nb.modal id="wedding-import" title="رفع الأفراح من ملف إكسل" icon="upload" :action="route('weddings.bulk')" files submit="رفع الملف">
        <div class="nb-stack-sm">
            <x-nb.field label="ملف الإكسل" name="import" type="file" accept=".xlsx,.xls,.csv" required />
            <div class="nb-alert"><i data-feather="info"></i><span>الأعمدة: day، date، groom_name، pride_father_name، address، from_time، to_time. الصفوف اللي من غير تاريخ بتتساب.</span></div>
        </div>
    </x-nb.modal>

    @foreach ($weddings as $wedding)
        <x-nb.modal :id="'wedding-edit-'.$wedding->id" :title="'تعديل فرح '.$wedding->groom_name" icon="edit-2" :action="route('weddings.update')">
            <input type="hidden" name="id" value="{{ $wedding->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم العريس" name="groom_name" :value="$wedding->groom_name" :row="$wedding->id" required />
                <x-nb.field label="والد العروس" name="pride_father_name" :value="$wedding->pride_father_name" :row="$wedding->id" required />
                <x-nb.field label="تاريخ المناسبة" name="date" type="date" :value="$wedding->date" :row="$wedding->id" required />
                <x-nb.field label="اليوم" name="day" type="select" :options="$days" :value="$wedding->day" :row="$wedding->id" required />
                <x-nb.field label="من الساعة" name="from_time" type="time" :value="$wedding->from_time" :row="$wedding->id" required />
                <x-nb.field label="الى الساعة" name="to_time" type="time" :value="$wedding->to_time" :row="$wedding->id" required />
                <x-nb.field label="العنوان" name="address" :value="$wedding->address" :row="$wedding->id" required span="full" />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'wedding-delete-'.$wedding->id" :action="route('wedding.delete', $wedding->id)" :message="'هتحذف فرح '.$wedding->groom_name.'؟'" />
    @endforeach
@endpush
