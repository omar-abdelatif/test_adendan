@php
    use App\Modules\Cemeteries\Domain\Enums\TombKind;
    $unit = $tomb->kind->unit();
    $lahd = $tomb->kind === TombKind::Lahd;
    $date = fn ($d) => $d?->locale('ar')->translatedFormat('j F Y');
    $phone = $tomb->guard_phone ? '0'.ltrim(preg_replace('/\D/', '', $tomb->guard_phone), '0') : null;
    // One slot per place: men first, then women, then the free ones.
    $slots = function ($state, $burials) {
        $men = $burials->where('gender.value', 'male')->sum('places');
        $women = $burials->where('gender.value', 'female')->sum('places');
        return array_merge(array_fill(0, $men, 'is-m'), array_fill(0, $women, 'is-f'), array_fill(0, max(0, $state->capacity - $men - $women), ''));
    };
    $status = fn ($state) => match (true) {
        $state->isEmpty() => ['فاضية', 'green'],
        $state->isFull() => ['مليانة', 'terra'],
        default => ['فيها مكان', 'gold'],
    };
    $burialJson = fn ($b) => [
        'mode' => 'edit', 'past' => $b->cleared_at !== null, 'url' => route('cemeteries.burials.update', $b),
        'region_id' => $b->region_id, 'tomb_id' => $b->tomb_id, 'chamber_id' => $b->chamber_id, 'places' => $b->places,
        'name' => $b->name, 'gender' => $b->gender?->value, 'age' => $b->age, 'member_id' => $b->member?->member_id,
        'death_place' => $b->death_place, 'death_date' => $b->death_date?->format('Y-m-d'), 'burial_date' => $b->burial_date?->format('Y-m-d'),
        'burial_cost' => $b->burial_cost, 'washer' => $b->washer, 'carrier' => $b->carrier, 'notes' => $b->notes,
    ];
@endphp
@extends('layouts.master')
@section('title', $tomb->name)
@section('content')
    <x-nb.page :title="$tomb->name" :subtitle="$tomb->kind->label().' · '.$tomb->serves->label().' · '.count($chambers).' '.($lahd ? 'لحد' : 'عيون').($lahd ? '' : ' · كل عين 6 أماكن')"
               :crumbs="['المقابر' => route('cemeteries.index'), $tomb->region->name]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="trash-2" class="nb-out" open="#tomb-delete">حذف</x-nb.button>
            <x-nb.button variant="ghost" icon="edit-2" open="#tomb-edit">تعديل المقبرة</x-nb.button>
            <x-nb.button icon="moon" :data-burial-open="json_encode(['mode' => 'add', 'region_id' => $tomb->region_id, 'tomb_id' => $tomb->id])">تسجيل دفنة هنا</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <section class="nb-card nb-infobar">
            <span><span class="nb-muted">التربي:</span> <strong>{{ $tomb->guard_name ?: '—' }}</strong>
                @if ($phone) <a href="tel:{{ $phone }}" dir="ltr">{{ $phone }}</a>@endif</span>
            <span><span class="nb-muted">التكلفة السنوية:</span> <strong class="nb-num">{{ $tomb->annual_cost ? number_format($tomb->annual_cost).' ج.م' : '—' }}</strong></span>
            <span><span class="nb-muted">مشغول:</span> <strong class="nb-num">{{ $used }} من {{ $capacity }}</strong></span>
            <span><span class="nb-muted">آخر دفنة:</span> <strong>{{ $last ? $date($last) : '—' }}</strong></span>
            @if ($tomb->location)
                <a class="nb-infobar__end" href="{{ $tomb->location }}" target="_blank" rel="noopener"><i data-feather="map-pin"></i> الموقع على الخريطة</a>
            @endif
        </section>

        @if (count($chambers))
            <div class="nb-legend">
                <span><i class="nb-slot is-m"></i>رجال</span><span><i class="nb-slot is-f"></i>سيدات</span><span><i class="nb-slot"></i>فاضي</span>
            </div>
            <div @class(['nb-chambers', 'is-lahd' => $lahd])>
                @foreach ($chambers as $row)
                    @php([$label, $tone] = $status($row['state']))
                    <button type="button" class="nb-card nb-chamber" data-nb-open="#chamber-{{ $row['chamber']->id }}" aria-label="{{ $unit }} {{ $row['chamber']->number }}: {{ $label }}">
                        <span class="nb-chamber__head"><strong>{{ $unit }} {{ $row['chamber']->number }}</strong><x-nb.badge :tone="$tone">{{ $label }}</x-nb.badge></span>
                        <span class="nb-slots">@foreach ($slots($row['state'], $row['burials']) as $slot)<i @class(['nb-slot', $slot])></i>@endforeach</span>
                        @unless ($lahd)
                            <small><span class="nb-num">{{ $row['state']->used }} من {{ $row['state']->capacity }}</span>@if ($row['state']->men) · {{ $row['state']->men }} رجال @endif @if ($row['state']->women) · {{ $row['state']->women }} سيدات @endif</small>
                        @endunless
                        <small>آخر دفنة: {{ $row['state']->lastBurial ? $date($row['state']->lastBurial) : '—' }}</small>
                    </button>
                @endforeach
            </div>
        @else
            <x-nb.card>
                <x-nb.empty icon="grid" title="المقبرة مفيهاش عيون متسجلة" text="ضيف عدد العيون أو اللحود من تعديل المقبرة علشان تقدر تسجل دفنات فيها.">
                    <x-nb.button icon="edit-2" open="#tomb-edit">تعديل المقبرة</x-nb.button>
                </x-nb.empty>
            </x-nb.card>
        @endif
    </div>
@endsection

@push('modals')
    @include('pages.cemeteries.partials.tomb-form', ['tomb' => $tomb])
    @include('pages.cemeteries.partials.burial-form')
    <x-nb.confirm id="tomb-delete" :action="route('cemeteries.tombs.destroy', $tomb)" :message="'هتحذف '.$tomb->name.'؟ ده بيتم بس لو مفيش ولا دفنة متسجلة فيها.'" />

    @foreach ($chambers as $row)
        @php($chamber = $row['chamber'])
        @php($state = $row['state'])
        <x-nb.modal :id="'chamber-'.$chamber->id" :title="$unit.' '.$chamber->number.' · '.$tomb->name" icon="grid" class="nb-modal--drawer">
            <div class="nb-stack-sm">
                <p class="nb-muted" style="margin: 0">
                    {{ $lahd ? ($state->isEmpty() ? 'فاضي' : 'مشغول') : $state->used.' من '.$state->capacity.' أماكن مشغولة' }}
                    · آخر تطهير {{ $chamber->last_cleared_at ? $date($chamber->last_cleared_at) : '—' }}
                </p>
                <span class="nb-slots is-lg">@foreach ($slots($state, $row['burials']) as $slot)<i @class(['nb-slot', $slot])></i>@endforeach</span>

                <h3 class="nb-ops__title">المدفونين في {{ $lahd ? 'اللحد ده' : 'العين دي' }}</h3>
                @forelse ($row['burials'] as $burial)
                    <div class="nb-card nb-burial">
                        <x-nb.avatar :name="$burial->name" size="sm" />
                        <div class="nb-burial__main">
                            <strong>{{ $burial->name }}</strong>
                            <span>
                                {{ $burial->gender?->label() }} · اتدفن {{ $burial->burial_date ? $date($burial->burial_date) : '—' }}
                                @if ($burial->places > 1) · {{ $burial->places }} أماكن @endif
                                @if ($burial->member) · عضوية {{ $burial->member->member_id }} @endif
                            </span>
                        </div>
                        <span class="nb-burial__acts">
                            @foreach ($burial->files as $file)
                                <a class="nb-icon-btn" href="{{ route('cemeteries.files.show', $file) }}" target="_blank" rel="noopener" title="{{ \App\Models\BurialFile::KINDS[$file->kind] ?? 'ملف' }}"><i data-feather="{{ $file->kind === 'photo' ? 'image' : 'file-text' }}"></i></a>
                            @endforeach
                            <button type="button" class="nb-icon-btn" title="تعديل" data-burial-open='@json($burialJson($burial))'><i data-feather="edit-2"></i></button>
                            <button type="button" class="nb-icon-btn nb-icon-btn--danger" title="حذف" data-burial-delete='@json(["url" => route("cemeteries.burials.destroy", $burial), "name" => $burial->name])'><i data-feather="trash-2"></i></button>
                        </span>
                    </div>
                @empty
                    <p class="nb-muted" style="margin: 0">{{ $lahd ? 'اللحد فاضي.' : 'العين فاضية.' }}</p>
                @endforelse

                @unless ($state->isFull())
                    <x-nb.button icon="plus" :data-burial-open="json_encode(['mode' => 'add', 'region_id' => $tomb->region_id, 'tomb_id' => $tomb->id, 'chamber_id' => $chamber->id])">دفنة جديدة في {{ $lahd ? 'اللحد ده' : 'العين دي' }}</x-nb.button>
                @endunless

                @unless ($state->isEmpty())
                    <div class="nb-op nb-op--danger">
                        <span class="nb-op__icon" aria-hidden="true"><i data-feather="refresh-cw"></i></span>
                        <span class="nb-op__text"><strong>تطهير {{ $lahd ? 'اللحد' : 'العين' }}</strong><span>الدفنات هتتعلم إنها اتطهرت وتفضل في السجل، و{{ $lahd ? 'اللحد يرجع فاضي' : 'العين ترجع فاضية' }}</span></span>
                        <button type="button" class="nb-btn nb-btn--sm nb-btn--danger" data-nb-start="#clear-{{ $chamber->id }}">تطهير</button>
                    </div>
                @endunless
            </div>
            <x-slot:footer>
                <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>قفل</button>
            </x-slot:footer>
        </x-nb.modal>

        @unless ($state->isEmpty())
            <x-nb.confirm :id="'clear-'.$chamber->id" :action="route('cemeteries.chambers.clear', $chamber)" method="POST" title="تطهير" submit="أيوه، طهّر"
                          :message="'هتطهر '.$unit.' '.$chamber->number.'؟ '.$row['burials']->count().' دفنة هتتنقل للمتوفيين السابقين.'" />
        @endunless
    @endforeach
@endpush

@push('scripts')
    <script>
        // "تطهير" inside a chamber drawer: close the drawer, then ask for confirmation.
        $(document).on('click', '[data-nb-start]', function () {
            const drawer = this.closest('.nb-modal');
            if (drawer) nbModal.close('#' + drawer.id);
            nbModal.open($(this).data('nbStart'));
        });
    </script>
@endpush
