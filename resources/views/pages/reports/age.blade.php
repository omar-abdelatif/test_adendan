@php
    $bands = \App\Models\Subscribers::query()->billable()->whereNotNull('birthdate')
        ->selectRaw("CASE
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 25 THEN 1
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 35 THEN 2
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 45 THEN 3
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 55 THEN 4
            WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 65 THEN 5
            ELSE 6 END AS band, COUNT(*) AS total")
        ->groupBy('band')->pluck('total', 'band');
    $names = [1 => 'أقل من 25', 2 => '25 – 34', 3 => '35 – 44', 4 => '45 – 54', 5 => '55 – 64', 6 => '65 فأكتر'];
    $max = max(1, $bands->max() ?? 1);
    $results = $ages ?? collect();
@endphp
@extends('layouts.master')
@section('title', 'تقرير السن')
@section('content')
    <x-nb.page title="تقرير السن" subtitle="الأعضاء حسب سنة الميلاد، وتوزيعهم على الأعمار" :crumbs="['التقارير']" />

    <div class="nb-split">
        <div class="nb-stack">
            <x-nb.card title="بحث بسنة الميلاد" icon="search">
                <form action="{{ route('reports.age') }}" method="GET" class="nb-row" style="align-items: flex-end">
                    <x-nb.field label="سنة الميلاد" name="birthdate" type="number" min="1900" :max="now()->year" :value="$ageKey ?? null" placeholder="مثلاً 1985" style="width: 220px" />
                    <x-nb.button type="submit" icon="search">بحث</x-nb.button>
                </form>
            </x-nb.card>

            <x-nb.card :title="isset($ageKey) && $ageKey ? 'مواليد '.$ageKey : 'النتايج'" :subtitle="$results->isNotEmpty() ? $results->count().' عضو' : null" icon="users" flush>
                @if ($results->isEmpty())
                    <x-nb.empty icon="search" :title="$message ?? 'اكتب سنة ميلاد عشان تشوف الأعضاء'" />
                @else
                    <x-nb.table id="age-table" :order="[[2, 'asc']]">
                        <thead><tr><th>رقم العضوية</th><th>العضو</th><th>تاريخ الميلاد</th><th>السن</th><th>الحالة</th></tr></thead>
                        <tbody>
                            @foreach ($results as $age)
                                <tr>
                                    <td class="nb-num">{{ $age->member_id }}</td>
                                    <td class="nb-strong">{{ $age->name }}</td>
                                    <td class="nb-num">{{ $age->birthdate }}</td>
                                    <td class="nb-num">{{ $age->birthdate ? \Illuminate\Support\Carbon::parse($age->birthdate)->age : '—' }}</td>
                                    <td><x-nb.member-status :status="$age->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-nb.table>
                @endif
            </x-nb.card>
        </div>

        <x-nb.card title="الأعضاء حسب السن" icon="bar-chart-2" tone="gold">
            <div class="nb-bars">
                @foreach ($names as $band => $label)
                    <div class="nb-bars__row">
                        <span>{{ $label }}</span>
                        <span class="nb-bars__track"><span style="width: {{ round(($bands[$band] ?? 0) / $max * 100) }}%"></span></span>
                        <span class="nb-bars__value">{{ number_format($bands[$band] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
        </x-nb.card>
    </div>
@endsection
