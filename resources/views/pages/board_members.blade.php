@php($photo = fn ($m) => $m->img ? asset('assets/images/border-photos/'.$m->img) : null)
@extends('layouts.master')
@section('title', 'مجلس الإدارة')
@section('content')
    <x-nb.page title="مجلس الإدارة" subtitle="أعضاء مجلس الإدارة اللي بيظهروا في التطبيق والموقع" :crumbs="['الجمعية والدليل']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#board-add">إضافة عضو</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    @if ($members->isEmpty())
        <x-nb.card><x-nb.empty icon="award" title="لسه مفيش أعضاء مجلس إدارة" text="ابدأ بإضافة رئيس المجلس وباقي الأعضاء." /></x-nb.card>
    @else
        <div class="nb-cols" style="--cols: 3">
            @foreach ($members as $member)
                <x-nb.card>
                    <div class="nb-row" style="align-items: flex-start; flex-wrap: nowrap">
                        <x-nb.avatar :name="$member->name" :src="$photo($member)" size="lg" />
                        <div style="flex: 1; min-width: 0">
                            <h3 style="font-size: 17px">{{ $member->name }}</h3>
                            <x-nb.badge tone="gold">{{ $member->position }}</x-nb.badge>
                            <div class="nb-muted nb-num" dir="ltr" style="margin-top: 8px; text-align: right">0{{ ltrim((string) $member->phone_number, '0') }}</div>
                        </div>
                    </div>
                    <x-slot:footer>
                        <x-nb.button variant="ghost" size="sm" icon="edit-2" :open="'#board-edit-'.$member->id">تعديل</x-nb.button>
                        <x-nb.button variant="ghost" size="sm" icon="trash-2" class="nb-out" :open="'#board-delete-'.$member->id">حذف</x-nb.button>
                    </x-slot:footer>
                </x-nb.card>
            @endforeach
        </div>
    @endif
@endsection

@push('modals')
    <x-nb.modal id="board-add" title="إضافة عضو مجلس إدارة" icon="plus" :action="route('board.store')" files submit="إضافة">
        <div class="nb-form-grid">
            <x-nb.field label="إسم العضو" name="name" required />
            <x-nb.field label="المنصب" name="position" required placeholder="مثلاً: أمين الصندوق" />
            <x-nb.field label="رقم المحمول" name="phone_number" type="tel" inputmode="numeric" dir="ltr" required />
            <x-nb.field label="الصورة" name="img" type="file" accept="image/*" required />
        </div>
    </x-nb.modal>

    @foreach ($members as $member)
        <x-nb.modal :id="'board-edit-'.$member->id" :title="'تعديل '.$member->name" icon="edit-2" :action="route('board.update')" files>
            <input type="hidden" name="id" value="{{ $member->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم العضو" name="name" :value="$member->name" :row="$member->id" required />
                <x-nb.field label="المنصب" name="position" :value="$member->position" :row="$member->id" required />
                <x-nb.field label="رقم المحمول" name="phone_number" type="tel" inputmode="numeric" dir="ltr" :value="'0'.ltrim((string) $member->phone_number, '0')" :row="$member->id" required />
                <x-nb.field label="صورة جديدة (اختياري)" name="img" type="file" accept="image/*" :row="$member->id" hint="سيبها فاضية لو مش عايز تغيّر الصورة" />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'board-delete-'.$member->id" :action="route('board.delete', $member->id)" :message="'هتحذف '.$member->name.' من مجلس الإدارة؟'" />
    @endforeach
@endpush
