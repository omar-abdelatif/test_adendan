@php
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—';
    $active = $associates->where('status', 1)->count();
@endphp
@extends('layouts.master')
@section('title', 'تقرير المنتسبين')
@section('content')
    <x-nb.page title="المنتسبين" subtitle="الأعضاء بعضوية إنتساب" :crumbs="['التقارير']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="إجمالي المنتسبين" :value="number_format(count($associates))" icon="user-check" main />
            <x-nb.stat label="اشتراك مفعل" :value="number_format($active)" icon="check-circle" tone="green" :progress="count($associates) ? round($active / count($associates) * 100) : 0" />
            <x-nb.stat label="غير مفعل أو معلق" :value="number_format($associates->whereIn('status', [0, 3])->count())" icon="pause-circle" tone="gold" />
        </x-nb.stats>

        <x-nb.card title="كل المنتسبين" icon="users" flush>
            <x-nb.table id="associates-table" :order="[[0, 'asc']]">
                <thead>
                    <tr><th>رقم العضوية</th><th>العضو</th><th>رقم المحمول</th><th>العنوان</th><th>تاريخ الميلاد</th><th>الوظيفة</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @foreach ($associates as $associate)
                        <tr>
                            <td class="nb-num">{{ $associate->member_id }}</td>
                            <td><div class="nb-cell-main"><x-nb.avatar :name="$associate->name" size="sm" /><strong>{{ $associate->name }}</strong></div></td>
                            <td class="nb-num" dir="ltr">{{ $phone($associate->mobile_no) }}</td>
                            <td>{{ $associate->address ?: '—' }}</td>
                            <td class="nb-num">{{ $associate->birthdate }}</td>
                            <td>{{ $associate->job ?: '—' }}</td>
                            <td><x-nb.member-status :status="$associate->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection
