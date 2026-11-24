@php
    $pct = fn (array $p) => $p['total'] ? (int) round(($p['total'] - $p['free']) / $p['total'] * 100) : 0;
    $date = fn ($d) => $d?->locale('ar')->translatedFormat('j F Y');
@endphp
@extends('layouts.master')
@section('title', 'المقابر')
@section('content')
    <x-nb.page title="المقابر" :subtitle="$totals['regions'].' مناطق · الأماكن الفاضية دلوقتي للرجال والسيدات في كل منطقة'" :crumbs="['المقابر']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="map-pin" open="#region-add">منطقة جديدة</x-nb.button>
            <x-nb.button variant="ghost" icon="plus" open="#tomb-add">مقبرة جديدة</x-nb.button>
            <x-nb.button icon="moon" :data-burial-open="json_encode(['mode' => 'add'])">تسجيل دفنة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="المقابر" :value="number_format($totals['tombs'])" icon="grid" :note="'في '.$totals['regions'].' مناطق، غير القرية'" />
            <x-nb.stat label="أماكن فاضية للرجال" :value="number_format($totals['freeMen'])" icon="users" :note="'من '.number_format($totals['totalMen']).' مكان'" />
            <x-nb.stat label="أماكن فاضية للسيدات" :value="number_format($totals['freeWomen'])" icon="users" tone="terra" :note="'من '.number_format($totals['totalWomen']).' مكان'" />
            <x-nb.stat label="دفنات السنة دي" :value="number_format($totals['thisYear'])" icon="moon" tone="ink" :note="$recent->first() ? 'آخرها '.$date($recent->first()->burial_date) : 'مفيش دفنات متسجلة لسه'" />
        </x-nb.stats>

        <div class="nb-regions">
            @foreach ($regions as $card)
                <section @class(['nb-card', 'nb-region', 'is-tight' => $card['nearlyFull']])>
                    <div class="nb-region__head">
                        <span class="nb-card__icon" aria-hidden="true"><i data-feather="map"></i></span>
                        <div style="flex: 1; min-width: 0">
                            <strong>{{ $card['region']->name }}</strong>
                            <span class="nb-muted" style="font-size: 12.5px">
                                {{ $card['tombs']->count() === 1 ? 'مقبرة واحدة' : ($card['tombs']->isEmpty() ? 'مفيش مقابر لسه' : $card['tombs']->count().' مقابر') }}@if ($card['kinds']) · {{ $card['kinds'] }}@endif
                            </span>
                        </div>
                        @if ($card['nearlyFull'])
                            <x-nb.badge tone="terra">قربت تتملي</x-nb.badge>
                        @endif
                    </div>
                    @if ($card['tombs']->isNotEmpty())
                        <div class="nb-region__tombs">
                            @foreach ($card['tombs'] as $tomb)
                                @php($own = $card['tombPlaces'][$tomb->id])
                                <a class="nb-tombrow" href="{{ route('cemeteries.tombs.show', $tomb) }}" aria-label="ادخل {{ $tomb->name }}">
                                    <span class="nb-tombrow__icon" aria-hidden="true"><i data-feather="grid"></i></span>
                                    <span class="nb-tombrow__main">
                                        <strong>{{ $tomb->name }}</strong>
                                        <span>
                                            {{ $tomb->kind->label() }} · {{ $tomb->serves->label() }} ·
                                            @if ($own['chambers'])
                                                <span class="nb-num">{{ $own['free'] }}</span> فاضي من <span class="nb-num">{{ $own['total'] }}</span>
                                            @else
                                                مفيش عيون متسجلة
                                            @endif
                                        </span>
                                    </span>
                                    <span class="nb-tombrow__go">ادخل<i data-feather="chevron-left"></i></span>
                                </a>
                            @endforeach
                        </div>
                        @foreach (['men' => ['رجال', ''], 'women' => ['سيدات', 'is-women']] as $key => [$label, $class])
                            @if ($card[$key]['total'] > 0)
                                <div>
                                    <div class="nb-meter__row"><span>{{ $label }}</span><span class="nb-num">{{ $card[$key]['free'] }} فاضي من {{ $card[$key]['total'] }}</span></div>
                                    <span @class(['nb-meter__bar', $class]) role="img" aria-label="{{ $label }}: {{ $pct($card[$key]) }}% مشغول"><span style="width: {{ $pct($card[$key]) }}%"></span></span>
                                </div>
                            @else
                                <div class="nb-meter__none">{{ $label }}: {{ $card['tombs']->every(fn ($t) => $t->chambers->isEmpty()) ? 'مفيش عيون متسجلة' : 'المقابر هنا مش لل'.$label }}</div>
                            @endif
                        @endforeach
                    @else
                        <x-nb.button variant="soft" size="sm" icon="plus" open="#tomb-add">ضيف مقبرة</x-nb.button>
                    @endif
                    <div class="nb-region__foot">
                        <span>آخر دفنة: {{ $card['last'] ? $date($card['last']) : '—' }}</span>
                    </div>
                </section>
            @endforeach

            @if ($village)
                <section class="nb-card nb-region nb-region--village">
                    <div class="nb-region__head">
                        <span class="nb-card__icon nb-tone-gold" aria-hidden="true"><i data-feather="home"></i></span>
                        <div><strong>{{ $village['region']->name }}</strong><span class="nb-muted" style="font-size: 12.5px">سجل دفنات من غير عيون</span></div>
                    </div>
                    <div class="nb-stat__value nb-num" style="font-size: 24px">{{ number_format($village['count']) }} <small>دفنة</small></div>
                    <span class="nb-muted" style="font-size: 12.5px">آخر دفنة: {{ $village['last'] ? $date($village['last']) : '—' }}</span>
                    <div class="nb-row" style="margin-top: auto">
                        <x-nb.button variant="soft" size="sm" icon="plus" :data-burial-open="json_encode(['mode' => 'add', 'region_id' => $village['region']->id])">دفنة في القرية</x-nb.button>
                        <x-nb.button variant="ghost" size="sm" :href="route('cemeteries.burials.index', ['tab' => 'village'])">السجل</x-nb.button>
                    </div>
                </section>
            @endif
        </div>

        <x-nb.card title="آخر الدفنات" icon="moon" tone="ink" flush>
            <x-slot:actions>
                <x-nb.button variant="ghost" size="sm" :href="route('cemeteries.burials.index')">كل المتوفيين</x-nb.button>
            </x-slot:actions>
            @if ($recent->isEmpty())
                <x-nb.empty icon="moon" title="مفيش دفنات متسجلة لسه" text="سجّل أول دفنة من زرار تسجيل دفنة." />
            @else
                <x-nb.table plain>
                    <thead><tr><th>الاسم</th><th>النوع</th><th>المكان</th><th>تاريخ الدفن</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $burial)
                            <tr>
                                <td class="nb-strong">{{ $burial->name }}</td>
                                <td>@if ($burial->gender)<x-nb.badge :tone="$burial->gender->value === 'male' ? 'teal' : 'terra'">{{ $burial->gender->label() }}</x-nb.badge>@else — @endif</td>
                                <td>
                                    @if ($burial->tomb)
                                        <a href="{{ route('cemeteries.tombs.show', $burial->tomb) }}">{{ $burial->placeLabel() }}</a>
                                    @else
                                        {{ $burial->placeLabel() }}
                                    @endif
                                </td>
                                <td class="nb-num">{{ $burial->burial_date?->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection

@push('modals')
    @include('pages.cemeteries.partials.tomb-form', ['tomb' => null])
    @include('pages.cemeteries.partials.burial-form')
    <x-nb.modal id="region-add" title="منطقة جديدة" icon="map-pin" size="sm" :action="route('cemeteries.regions.store')" submit="إضافة">
        <x-nb.field label="اسم المنطقة" name="name" required placeholder="مثلاً: العاشر من رمضان" />
    </x-nb.modal>
@endpush
