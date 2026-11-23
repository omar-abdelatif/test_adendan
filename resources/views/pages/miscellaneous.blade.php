@php
    $categories = ['كهرباء', 'مياه', 'غاز', 'تلفون ارضي', 'انترنت ارضي', 'أخرى'];
    $receipt = fn ($m) => $m->invoice_img ? asset('assets/images/miscellaneous/'.$m->invoice_img) : null;
    $thisMonth = $miscellaneous->filter(fn ($m) => $m->created_at?->isSameMonth(now()));
@endphp
@extends('layouts.master')
@section('title', 'النثريات')
@section('content')
    <x-nb.page title="النثريات" subtitle="فواتير ومصروفات المقر، وكل فاتورة معاها صورة الإيصال" :crumbs="['الأعضاء والمالية']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#misc-add">إضافة فاتورة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="مصروف الشهر ده" :value="number_format($thisMonth->sum('amount'))" unit="ج.م" icon="calendar" tone="terra" :note="$thisMonth->count().' فاتورة'" />
            <x-nb.stat label="كل المصروفات" :value="number_format($miscellaneous->sum('amount'))" unit="ج.م" icon="file-text" />
            <x-nb.stat label="عدد الفواتير" :value="number_format($miscellaneous->count())" icon="layers" tone="gold" />
        </x-nb.stats>

        <x-nb.card title="كل الفواتير" icon="file-text" flush>
            <x-slot:actions>
                <div class="nb-chips">
                    <a href="#" class="nb-chip is-on" data-nb-filter="#misc-table" data-col="1" data-value="">الكل</a>
                    @foreach ($categories as $category)
                        <a href="#" class="nb-chip" data-nb-filter="#misc-table" data-col="1" data-value="{{ $category }}">{{ $category }} <em>{{ $miscellaneous->where('category', $category)->count() }}</em></a>
                    @endforeach
                </div>
            </x-slot:actions>
            <x-nb.table id="misc-table" :order="[[3, 'desc']]">
                <thead>
                    <tr>
                        <th class="no-sort">الإيصال</th>
                        <th>التصنيف</th>
                        <th>التفاصيل</th>
                        <th>التاريخ</th>
                        <th class="is-end">المبلغ</th>
                        <th class="no-sort is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($miscellaneous as $misc)
                        <tr>
                            <td>
                                @if ($receipt($misc))
                                    <a href="{{ $receipt($misc) }}" target="_blank" rel="noopener"><img class="nb-thumb" src="{{ $receipt($misc) }}" alt="إيصال" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'nb-muted',textContent:'—'}))"></a>
                                @else
                                    <span class="nb-muted">—</span>
                                @endif
                            </td>
                            <td><x-nb.badge tone="teal">{{ $misc->category }}</x-nb.badge></td>
                            <td>{{ $misc->other_category ?: '—' }}</td>
                            <td data-order="{{ $misc->created_at }}" class="nb-nowrap nb-muted">{{ $misc->created_at?->locale('ar')->translatedFormat('j F Y') }}</td>
                            <td class="is-end nb-out">{{ number_format($misc->amount) }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#misc-edit-'.$misc->id" />
                                    <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#misc-delete-'.$misc->id" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-nb.table>
        </x-nb.card>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="misc-add" title="إضافة فاتورة جديدة" icon="plus" :action="route('miscellaneous.store')" files submit="حفظ الفاتورة">
        <div class="nb-form-grid">
            <x-nb.field label="التصنيف" name="category" type="select" :options="$categories" required />
            <x-nb.field label="المبلغ" name="amount" type="number" min="1" inputmode="numeric" required />
            <div class="nb-span-full" data-nb-when="category:أخرى"><x-nb.field label="التصنيف الأخر" name="other_category" placeholder="مثلاً: صيانة سماعات القاعة" /></div>
            <x-nb.field label="صورة الفاتورة / الإيصال" name="invoice_img" type="file" accept="image/*" required span="full" />
            <div class="nb-alert nb-alert--gold nb-span-full"><i data-feather="info"></i><span>المبلغ هيتخصم من الخزنة أول ما تحفظ.</span></div>
        </div>
    </x-nb.modal>

    @foreach ($miscellaneous as $misc)
        <x-nb.modal :id="'misc-edit-'.$misc->id" :title="'تعديل فاتورة '.$misc->category" icon="edit-2" :action="route('miscellaneous.update')" files>
            <input type="hidden" name="id" value="{{ $misc->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="التصنيف" name="category" type="select" :options="$categories" :value="$misc->category" :row="$misc->id" required />
                <x-nb.field label="المبلغ" name="amount" type="number" min="1" inputmode="numeric" :value="$misc->amount" :row="$misc->id" required />
                <div class="nb-span-full" data-nb-when="category:أخرى"><x-nb.field label="التصنيف الأخر" name="other_category" :value="$misc->other_category" :row="$misc->id" /></div>
                <x-nb.field label="صورة جديدة (اختياري)" name="invoice_img" type="file" accept="image/*" :row="$misc->id" span="full" />
                <div class="nb-alert nb-alert--gold nb-span-full"><i data-feather="info"></i><span>لو غيّرت المبلغ، الفرق بيتسجل في الخزنة كحركة تعديل.</span></div>
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'misc-delete-'.$misc->id" :action="route('miscellaneous.delete', $misc->id)" :message="'هتحذف فاتورة '.$misc->category.' بمبلغ '.number_format($misc->amount).' ج.م؟'">
            <span class="nb-muted">المبلغ هيرجع للخزنة.</span>
        </x-nb.confirm>
    @endforeach
@endpush
