@php
    $groups = [
        ['id' => 'ssn', 'label' => 'ناقص الرقم القومي', 'icon' => 'credit-card', 'rows' => $incompleteSSN, 'count' => $incompleteSSNCount, 'field' => 'ssn', 'head' => 'الرقم القومي'],
        ['id' => 'mobile', 'label' => 'ناقص رقم المحمول', 'icon' => 'phone', 'rows' => $incompleteMobile, 'count' => $incompleteMobileCount, 'field' => 'mobile_no', 'head' => 'رقم المحمول'],
        ['id' => 'address', 'label' => 'ناقص العنوان', 'icon' => 'map-pin', 'rows' => $incompleteAddress, 'count' => $incompleteAddressCount, 'field' => 'address', 'head' => 'العنوان'],
    ];
@endphp
@extends('layouts.master')
@section('title', 'البيانات الناقصة')
@section('content')
    <x-nb.page title="البيانات الناقصة" subtitle="أعضاء ناقصهم بيانات أساسية؛ افتح ملف العضو وكمّلها" :crumbs="['التقارير']" />

    <div class="nb-stack">
        <x-nb.stats>
            @foreach ($groups as $g)
                <x-nb.stat :label="$g['label']" :value="number_format($g['count'])" :icon="$g['icon']" :tone="$g['count'] ? 'terra' : 'green'" />
            @endforeach
        </x-nb.stats>

        <section class="nb-card">
            <div class="nb-tabs" data-nb-tabs role="tablist">
                @foreach ($groups as $g)
                    <button type="button" @class(['is-on' => $loop->first]) data-nb-tab="#missing-{{ $g['id'] }}" role="tab"><i data-feather="{{ $g['icon'] }}"></i>{{ $g['label'] }} <em>{{ $g['count'] }}</em></button>
                @endforeach
            </div>
            @foreach ($groups as $g)
                <div id="missing-{{ $g['id'] }}" role="tabpanel" @unless ($loop->first) hidden @endunless>
                    @if ($g['rows']->isEmpty())
                        <x-nb.empty icon="check-circle" title="كل الأعضاء بياناتهم كاملة هنا" />
                    @else
                        <x-nb.table :id="'missing-table-'.$g['id']" :order="[[0, 'asc']]">
                            <thead><tr><th>رقم العضوية</th><th>العضو</th><th>{{ $g['head'] }}</th><th class="no-sort is-actions"></th></tr></thead>
                            <tbody>
                                @foreach ($g['rows'] as $row)
                                    <tr>
                                        <td class="nb-num">{{ $row->member_id }}</td>
                                        <td class="nb-strong">{{ $row->name }}</td>
                                        <td>{!! filled($row->{$g['field']}) ? e($row->{$g['field']}) : '<span class="nb-badge nb-badge--terra">ناقص</span>' !!}</td>
                                        <td class="is-actions"><x-nb.button size="sm" variant="soft" icon="edit-2" :href="route('subscriber.details', $row->id)">كمّل البيانات</x-nb.button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-nb.table>
                    @endif
                </div>
            @endforeach
        </section>
    </div>
@endsection
