@php
    $top = \App\Models\Subscribers::query()->billable()->whereNotNull('address')->where('address', '!=', '')
        ->selectRaw('address, COUNT(*) AS total')->groupBy('address')->orderByDesc('total')->limit(10)->pluck('total', 'address');
    $max = max(1, $top->max() ?? 1);
    $results = $locations ?? collect();
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—';
@endphp
@extends('layouts.master')
@section('title', 'تقرير السكن')
@section('content')
    <x-nb.page title="تقرير السكن" subtitle="الأعضاء ساكنين فين، عشان التواصل والمناسبات" :crumbs="['التقارير']" />

    <div class="nb-split">
        <div class="nb-stack">
            <x-nb.card title="بحث بالمنطقة" icon="search">
                <form action="{{ route('reports.location') }}" method="GET" class="nb-row" style="align-items: flex-end">
                    <x-nb.field label="المنطقة أو العنوان" name="address" :value="$locationKey ?? null" placeholder="مثلاً: عين شمس" style="width: 280px" />
                    <x-nb.button type="submit" icon="search">بحث</x-nb.button>
                </form>
            </x-nb.card>

            <x-nb.card :title="isset($locationKey) && $locationKey ? 'ساكنين في «'.$locationKey.'»' : 'النتايج'" :subtitle="$results->isNotEmpty() ? $results->count().' عضو' : null" icon="map-pin" flush>
                @if ($results->isEmpty())
                    <x-nb.empty icon="map" :title="$message ?? 'اكتب اسم منطقة عشان تشوف الأعضاء'" />
                @else
                    <x-nb.table id="location-table" :order="[[0, 'asc']]">
                        <thead><tr><th>رقم العضوية</th><th>العضو</th><th>العنوان</th><th>رقم المحمول</th><th>الحالة</th></tr></thead>
                        <tbody>
                            @foreach ($results as $item)
                                <tr>
                                    <td class="nb-num">{{ $item->member_id }}</td>
                                    <td class="nb-strong">{{ $item->name }}</td>
                                    <td>{{ $item->address }}</td>
                                    <td class="nb-num" dir="ltr">{{ $phone($item->mobile_no) }}</td>
                                    <td><x-nb.member-status :status="$item->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-nb.table>
                @endif
            </x-nb.card>
        </div>

        <x-nb.card title="أكتر المناطق" icon="map" tone="gold">
            <div class="nb-bars">
                @foreach ($top as $place => $total)
                    <div class="nb-bars__row">
                        <a href="{{ route('reports.location', ['address' => $place]) }}">{{ $place }}</a>
                        <span class="nb-bars__track"><span style="width: {{ round($total / $max * 100) }}%"></span></span>
                        <span class="nb-bars__value">{{ number_format($total) }}</span>
                    </div>
                @endforeach
            </div>
        </x-nb.card>
    </div>
@endsection
