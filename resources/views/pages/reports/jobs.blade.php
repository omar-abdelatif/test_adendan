@php
    $top = \App\Models\Subscribers::query()->billable()->whereNotNull('job')->where('job', '!=', '')
        ->selectRaw('job, COUNT(*) AS total')->groupBy('job')->orderByDesc('total')->limit(10)->pluck('total', 'job');
    $max = max(1, $top->max() ?? 1);
    $results = $jobs ?? collect();
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—';
@endphp
@extends('layouts.master')
@section('title', 'تقرير الوظائف')
@section('content')
    <x-nb.page title="تقرير الوظائف" subtitle="المهن والوظائف بتاعة الأعضاء" :crumbs="['التقارير']" />

    <div class="nb-split">
        <div class="nb-stack">
            <x-nb.card title="بحث بالوظيفة" icon="search">
                <form action="{{ route('reports.jobs') }}" method="GET" class="nb-row" style="align-items: flex-end">
                    <x-nb.field label="الوظيفة" name="search" type="search" :value="$keyword ?? null" placeholder="مثلاً: مهندس" style="width: 280px" />
                    <x-nb.button type="submit" icon="search">بحث</x-nb.button>
                </form>
            </x-nb.card>

            <x-nb.card :title="isset($keyword) && $keyword ? 'وظيفة «'.$keyword.'»' : 'النتايج'" :subtitle="$results->isNotEmpty() ? $results->count().' عضو' : null" icon="briefcase" flush>
                @if ($results->isEmpty())
                    <x-nb.empty icon="briefcase" :title="$message ?? 'اكتب اسم وظيفة عشان تشوف الأعضاء'" />
                @else
                    <x-nb.table id="jobs-table" :order="[[0, 'asc']]">
                        <thead><tr><th>العضو</th><th>الوظيفة</th><th>الرقم القومي</th><th>رقم المحمول</th><th>السكن</th><th>الحالة</th></tr></thead>
                        <tbody>
                            @foreach ($results as $item)
                                <tr>
                                    <td class="nb-strong">{{ $item->name }}</td>
                                    <td><x-nb.badge tone="teal">{{ $item->job }}</x-nb.badge></td>
                                    <td class="nb-num">{{ $item->ssn }}</td>
                                    <td class="nb-num" dir="ltr">{{ $phone($item->mobile_no) }}</td>
                                    <td>{{ $item->address ?: '—' }}</td>
                                    <td><x-nb.member-status :status="$item->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-nb.table>
                @endif
            </x-nb.card>
        </div>

        <x-nb.card title="أكتر الوظائف" icon="bar-chart-2" tone="gold">
            <div class="nb-bars">
                @foreach ($top as $job => $total)
                    <div class="nb-bars__row">
                        <a href="{{ route('reports.jobs', ['search' => $job]) }}">{{ $job }}</a>
                        <span class="nb-bars__track"><span style="width: {{ round($total / $max * 100) }}%"></span></span>
                        <span class="nb-bars__value">{{ number_format($total) }}</span>
                    </div>
                @endforeach
            </div>
        </x-nb.card>
    </div>
@endsection
