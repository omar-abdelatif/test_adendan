@php
    $burialJson = fn ($b) => [
        'mode' => 'edit', 'past' => $b->cleared_at !== null, 'url' => route('cemeteries.burials.update', $b),
        'region_id' => $b->region_id, 'tomb_id' => $b->tomb_id, 'chamber_id' => $b->chamber_id, 'places' => $b->places,
        'name' => $b->name, 'gender' => $b->gender?->value, 'age' => $b->age, 'member_id' => $b->member?->member_id,
        'death_place' => $b->death_place, 'death_date' => $b->death_date?->format('Y-m-d'), 'burial_date' => $b->burial_date?->format('Y-m-d'),
        'burial_cost' => $b->burial_cost, 'washer' => $b->washer, 'carrier' => $b->carrier, 'notes' => $b->notes,
    ];
    $regionNames = $burials->pluck('region.name')->filter()->unique()->sort()->values();
    $subtitles = [
        'chambers' => 'المدفونين في العيون واللحود دلوقتي',
        'past' => 'اللي اتطهرت عيونهم، واللي اتسجلوا من السجلات القديمة',
        'village' => 'سجل دفنات القرية',
    ];
@endphp
@extends('layouts.master')
@section('title', 'المتوفيين')
@section('content')
    <x-nb.page title="المتوفيين" :subtitle="$subtitles[$tab]" :crumbs="['المقابر' => route('cemeteries.index')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="upload" open="#burials-import">رفع سابقين من إكسل</x-nb.button>
            <x-nb.button variant="ghost" icon="archive" :data-burial-open="json_encode(['mode' => 'past'])">متوفي سابق</x-nb.button>
            <x-nb.button icon="moon" :data-burial-open="json_encode(['mode' => 'add'])">تسجيل دفنة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <x-nb.card flush>
        <nav class="nb-tabs" aria-label="أنواع المتوفيين">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('cemeteries.burials.index', ['tab' => $key]) }}" @class(['is-on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>
                    {{ $label }} <x-nb.badge :tone="$tab === $key ? 'teal' : null">{{ number_format($counts[$key]) }}</x-nb.badge>
                </a>
            @endforeach
        </nav>
        @if ($regionNames->count() > 1)
            <div class="nb-chips" style="padding: 14px 18px 0">
                <a href="#" class="nb-chip is-on" data-nb-filter="#burials-table" data-col="2" data-value="">كل المناطق</a>
                @foreach ($regionNames as $name)
                    <a href="#" class="nb-chip" data-nb-filter="#burials-table" data-col="2" data-value="{{ $name }}">{{ $name }}</a>
                @endforeach
            </div>
        @endif
        @if ($burials->isEmpty())
            <x-nb.empty icon="moon" title="مفيش حد هنا لسه" :text="$tab === 'past' ? 'المتوفيين السابقين بيظهروا هنا لما تطهر عين، أو ترفعهم من إكسل.' : 'سجّل دفنة من زرار تسجيل دفنة.'" />
        @else
            <x-nb.table id="burials-table" :order="[[4, 'desc']]" :page-length="25">
                <thead>
                    <tr>
                        <th>الاسم</th><th>النوع</th><th>المنطقة</th><th>المكان</th><th>تاريخ الدفن</th>
                        <th>{{ $tab === 'past' ? 'اتنقل للسجل' : 'تاريخ الوفاة' }}</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($burials as $burial)
                        <tr>
                            <td>
                                <div class="nb-cell-main"><x-nb.avatar :name="$burial->name" size="sm" />
                                    <div><strong>{{ $burial->name }}</strong>@if ($burial->member)<small>عضوية {{ $burial->member->member_id }}</small>@endif</div>
                                </div>
                            </td>
                            <td>@if ($burial->gender)<x-nb.badge :tone="$burial->gender->value === 'male' ? 'teal' : 'terra'">{{ $burial->gender->label() }}</x-nb.badge>@else <span class="nb-muted">—</span> @endif</td>
                            <td>{{ $burial->region?->name }}</td>
                            <td>
                                @if ($burial->tomb)
                                    <a href="{{ route('cemeteries.tombs.show', $burial->tomb) }}">{{ $burial->tomb->name }}</a>@if ($burial->chamber) · {{ $burial->chamber->label() }}@endif
                                @else
                                    <span class="nb-muted">{{ $burial->notes ? \Illuminate\Support\Str::limit($burial->notes, 40) : '—' }}</span>
                                @endif
                            </td>
                            <td class="nb-num nb-nowrap" data-order="{{ $burial->burial_date?->format('Y-m-d') }}">{{ $burial->burial_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="nb-num nb-nowrap">{{ $tab === 'past' ? $burial->cleared_at?->format('Y-m-d') : ($burial->death_date?->format('Y-m-d') ?? '—') }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    @foreach ($burial->files as $file)
                                        <a class="nb-icon-btn" href="{{ route('cemeteries.files.show', $file) }}" target="_blank" rel="noopener" title="{{ \App\Models\BurialFile::KINDS[$file->kind] ?? 'ملف' }}"><i data-feather="{{ $file->kind === 'photo' ? 'image' : 'file-text' }}"></i></a>
                                    @endforeach
                                    <button type="button" class="nb-icon-btn" title="تعديل" data-burial-open='@json($burialJson($burial))'><i data-feather="edit-2"></i></button>
                                    <button type="button" class="nb-icon-btn nb-icon-btn--danger" title="حذف" data-burial-delete='@json(["url" => route("cemeteries.burials.destroy", $burial), "name" => $burial->name])'><i data-feather="trash-2"></i></button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        @endif
    </x-nb.card>
@endsection

@push('modals')
    @include('pages.cemeteries.partials.burial-form')
    <x-nb.modal id="burials-import" title="رفع متوفيين سابقين من إكسل" icon="upload" :action="route('cemeteries.burials.import')" files submit="رفع الملف">
        <div class="nb-stack-sm">
            <x-nb.field label="ملف الإكسل" name="sheet" type="file" accept=".xlsx,.xls" required />
            <div class="nb-alert">
                <i data-feather="info"></i>
                <span>نفس أعمدة النظام القديم: <span dir="ltr">name, death_date, burial_date, region, tomb</span>، وممكن تضيف <span dir="ltr">gender</span> (ذكر أو أنثى) و<span dir="ltr">place</span>. السطر اللي منطقته مش موجودة، أو متسجل قبل كده، بيتساب وبيظهرلك.</span>
            </div>
        </div>
    </x-nb.modal>
@endpush
