@php
    $events = ['created' => ['إضافة', 'green'], 'updated' => ['تعديل', 'gold'], 'deleted' => ['حذف', 'terra']];
    $who = fn ($a) => $a->custom_causer ?? $a->causer?->name ?? 'السيستم';
    $details = fn ($a) => is_array($a->properties) ? $a->properties : (json_decode((string) $a->properties, true) ?: []);
@endphp
@extends('layouts.master')
@section('title', 'سجل النشاطات')
@section('content')
    <x-nb.page title="سجل النشاطات" subtitle="كل تعديل حصل في الداشبورد، مين عمله وإمتى" :crumbs="['الإدارة']" />

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="كل النشاطات" :value="number_format($activities->count())" icon="activity" />
            <x-nb.stat label="النهارده" :value="number_format($activities->filter(fn ($a) => $a->created_at?->isToday())->count())" icon="sun" tone="gold" />
            <x-nb.stat label="عمليات حذف" :value="number_format($activities->where('event', 'deleted')->count())" icon="trash-2" tone="terra" />
        </x-nb.stats>

        <x-nb.card title="النشاطات" icon="activity" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#activity-table" data-col="1" data-value="">الكل</a>
                    @foreach ($events as [$label])
                        <a href="#" class="nb-chip" data-nb-filter="#activity-table" data-col="1" data-value="{{ $label }}">{{ $label }}</a>
                    @endforeach
                </div>
            </x-slot:actions>
            <x-nb.table id="activity-table" :order="[[3, 'desc']]" :page-length="25">
                <thead><tr><th>الوصف</th><th>الحدث</th><th>بواسطة</th><th>الوقت</th><th class="no-sort is-actions"></th></tr></thead>
                <tbody>
                    @foreach ($activities as $activity)
                        @php([$label, $tone] = $events[$activity->event] ?? [$activity->event ?: '—', null])
                        <tr>
                            <td class="nb-strong" style="min-width: 320px">{{ $activity->description }}</td>
                            <td><x-nb.badge :tone="$tone">{{ $label }}</x-nb.badge></td>
                            <td><div class="nb-cell-main"><x-nb.avatar :name="$who($activity)" size="sm" /><span>{{ $who($activity) }}</span></div></td>
                            <td class="nb-num nb-nowrap" data-order="{{ $activity->created_at }}">{{ $activity->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="is-actions">
                                @if (count($details($activity)))
                                    <button type="button" class="nb-icon-btn" data-nb-child aria-label="تفاصيل"><i data-feather="eye"></i></button>
                                    <template>
                                        <div class="nb-cols" style="padding: 6px 8px">
                                            @foreach ($details($activity) as $section => $properties)
                                                <div>
                                                    <div class="nb-section-title">{{ ['old' => 'قبل', 'attributes' => 'بعد'][$section] ?? $section }}</div>
                                                    <dl class="nb-kv">
                                                        @foreach ((array) $properties as $property => $value)
                                                            <dt>{{ $property }}</dt><dd>{{ is_scalar($value) && $value !== '' ? $value : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : 'لا يوجد') }}</dd>
                                                        @endforeach
                                                    </dl>
                                                </div>
                                            @endforeach
                                        </div>
                                    </template>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection
