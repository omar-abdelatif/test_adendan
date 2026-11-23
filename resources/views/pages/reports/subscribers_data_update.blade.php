@php
    $bySsn = \App\Models\Subscribers::whereIn('ssn', $ocr->pluck('nid')->filter())->get()->keyBy(fn ($m) => (string) $m->ssn);
    $compare = [['name', 'name', 'الاسم'], ['address', 'address', 'العنوان'], ['birth_date', 'birthdate', 'تاريخ الميلاد']];
@endphp
@extends('layouts.master')
@section('title', 'بيانات للمراجعة')
@section('content')
    <x-nb.page title="بيانات أعضاء للمراجعة" subtitle="تعديلات جاية من بحث الموقع، وبيانات بطاقات اتقرت بالـ OCR" :crumbs="['التقارير']" />

    <section class="nb-card">
        <div class="nb-tabs" data-nb-tabs role="tablist">
            <button type="button" class="is-on" data-nb-tab="#review-site" role="tab"><i data-feather="globe"></i>من الموقع <em>{{ $data->count() }}</em></button>
            <button type="button" data-nb-tab="#review-ocr" role="tab"><i data-feather="credit-card"></i>بطاقات (OCR) <em>{{ $ocr->count() }}</em></button>
        </div>

        <div id="review-site" role="tabpanel">
            @if ($data->isEmpty())
                <x-nb.empty icon="check-circle" title="مفيش بيانات من الموقع مستنية" />
            @else
                <x-nb.table id="site-table" :order="[[0, 'asc']]">
                    <thead><tr><th>العضو</th><th>رقم العضوية</th><th>رقم المحمول</th><th>الرقم القومي</th><th>العنوان</th><th>تاريخ الميلاد</th><th class="no-sort is-actions"></th></tr></thead>
                    <tbody>
                        @foreach ($data as $item)
                            <tr>
                                <td class="nb-strong">{{ $item->name }}</td>
                                <td class="nb-num">{{ $item->member_id ?? '—' }}</td>
                                <td class="nb-num" dir="ltr">{{ $item->mobile_no ?? '—' }}</td>
                                <td class="nb-num">{{ $item->ssn ?? '—' }}</td>
                                <td>{{ $item->address ?? '—' }}</td>
                                <td class="nb-num">{{ $item->birthdate ?? '—' }}</td>
                                <td class="is-actions">
                                    <div class="nb-row-actions">
                                        @if ($item->member_id)
                                            <form action="{{ route('search.approve') }}" method="POST" data-nb-ajax data-nb-remove="tr">
                                                @csrf
                                                <input type="hidden" name="member_id" value="{{ $item->member_id }}">
                                                <input type="hidden" name="mobile_no" value="{{ $item->mobile_no }}">
                                                <input type="hidden" name="ssn" value="{{ $item->ssn }}">
                                                <input type="hidden" name="address" value="{{ $item->address }}">
                                                <input type="hidden" name="birthdate" value="{{ $item->birthdate }}">
                                                <x-nb.icon-button type="submit" icon="check" label="موافقة وتحديث الملف" />
                                            </form>
                                        @endif
                                        <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#site-delete-'.$item->id" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            @endif
        </div>

        <div id="review-ocr" role="tabpanel" hidden>
            @if ($ocr->isEmpty())
                <x-nb.empty icon="check-circle" title="مفيش بطاقات مستنية" />
            @else
                <div class="nb-list">
                    @foreach ($ocr as $item)
                        @php($member = $bySsn[(string) $item->nid] ?? null)
                        <div class="nb-list__row" style="align-items: flex-start">
                            <x-nb.avatar :name="$item->name" />
                            <div class="nb-list__main">
                                <strong>{{ $member?->name ?? $item->name }} @if ($member)<span class="nb-muted">· عضوية {{ $member->member_id }}</span>@else<x-nb.badge tone="terra">مش لاقيين عضو بالرقم القومي ده</x-nb.badge>@endif</strong>
                                <span class="nb-num">الرقم القومي {{ $item->nid ?? '—' }}</span>
                                <div class="nb-stack-sm" style="margin-top: 8px">
                                    @foreach ($compare as [$ocrField, $memberField, $label])
                                        @php($fromCard = $item->{$ocrField})
                                        @php($onFile = $member?->{$memberField})
                                        @continue(blank($fromCard))
                                        <div class="nb-row" style="flex-wrap: nowrap; align-items: baseline">
                                            <span class="nb-label" style="width: 100px; flex: none">{{ $label }}</span>
                                            @if ((string) $fromCard === (string) $onFile)
                                                <span>{{ $fromCard }}</span><x-nb.badge tone="green">مطابق</x-nb.badge>
                                            @else
                                                <span class="nb-muted" style="text-decoration: line-through">{{ filled($onFile) ? $onFile : '—' }}</span>
                                                <i data-feather="arrow-left" class="nb-muted"></i>
                                                <span class="nb-in">{{ $fromCard }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="nb-row">
                                <form action="{{ route('reports.search.approve', $item->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="name" value="{{ $item->name }}">
                                    <input type="hidden" name="mobile" value="{{ $item->mobile }}">
                                    <input type="hidden" name="nid" value="{{ $item->nid }}">
                                    <input type="hidden" name="address" value="{{ $item->address }}">
                                    <input type="hidden" name="birthdate" value="{{ $item->birth_date }}">
                                    <x-nb.button type="submit" size="sm" icon="check" :disabled="! $member">اعتماد</x-nb.button>
                                </form>
                                <x-nb.button size="sm" variant="ghost" icon="trash-2" class="nb-out" :open="'#ocr-delete-'.$item->id">حذف</x-nb.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection

@push('modals')
    @foreach ($data as $item)
        <x-nb.confirm :id="'site-delete-'.$item->id" :action="route('search.delete', $item->id)" :message="'هتحذف طلب '.$item->name.'؟'" />
    @endforeach
    @foreach ($ocr as $item)
        <x-nb.confirm :id="'ocr-delete-'.$item->id" :action="route('search.ocr.delete', $item->id)" :message="'هتحذف بيانات بطاقة '.$item->name.'؟'" />
    @endforeach
@endpush
