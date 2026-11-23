@extends('layouts.master')
@section('title', 'اللجان')
@section('content')
    <x-nb.page title="لجان الجمعية" subtitle="كل لجنة، رئيسها، ومهامها" :crumbs="['الجمعية والدليل']">
        <x-slot:actions>
            <x-nb.button icon="plus" open="#committee-add">إضافة لجنة</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    @if ($associations->isEmpty())
        <x-nb.card><x-nb.empty icon="layers" title="لسه مفيش لجان" /></x-nb.card>
    @else
        <div class="nb-cols" style="--cols: 3">
            @foreach ($associations as $asso)
                <x-nb.card :title="$asso->name" icon="layers" :subtitle="'رئيس اللجنة: '.$asso->boss">
                    <p class="nb-muted" style="margin-bottom: 12px">{{ $asso->description ?: 'مفيش وصف.' }}</p>
                    @if ($asso->tasks)
                        <div class="nb-chips">
                            @foreach (preg_split('/[،,\n]+/u', $asso->tasks) as $task)
                                @if (trim($task) !== '')
                                    <x-nb.badge tone="teal">{{ trim($task) }}</x-nb.badge>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <x-slot:footer>
                        <x-nb.button variant="ghost" size="sm" icon="edit-2" :open="'#committee-edit-'.$asso->id">تعديل</x-nb.button>
                        <x-nb.button variant="ghost" size="sm" icon="trash-2" class="nb-out" :open="'#committee-delete-'.$asso->id">حذف</x-nb.button>
                    </x-slot:footer>
                </x-nb.card>
            @endforeach
        </div>
    @endif
@endsection

@push('modals')
    <x-nb.modal id="committee-add" title="إضافة لجنة جديدة" icon="plus" :action="route('association.store')" submit="إضافة اللجنة">
        <div class="nb-form-grid">
            <x-nb.field label="إسم اللجنة" name="name" required />
            <x-nb.field label="رئيس اللجنة" name="boss" required />
            <x-nb.field label="عن اللجنة" name="description" type="textarea" rows="3" span="full" />
            <x-nb.field label="مهام اللجنة" name="tasks" span="full" hint="افصل بين المهام بفاصلة ،" />
        </div>
    </x-nb.modal>

    @foreach ($associations as $asso)
        <x-nb.modal :id="'committee-edit-'.$asso->id" :title="'تعديل '.$asso->name" icon="edit-2" :action="route('association.update')">
            <input type="hidden" name="id" value="{{ $asso->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم اللجنة" name="name" :value="$asso->name" :row="$asso->id" required />
                <x-nb.field label="رئيس اللجنة" name="boss" :value="$asso->boss" :row="$asso->id" required />
                <x-nb.field label="عن اللجنة" name="description" type="textarea" rows="3" :value="$asso->description" :row="$asso->id" span="full" />
                <x-nb.field label="مهام اللجنة" name="tasks" :value="$asso->tasks" :row="$asso->id" span="full" hint="افصل بين المهام بفاصلة ،" />
            </div>
        </x-nb.modal>
        <x-nb.confirm :id="'committee-delete-'.$asso->id" :action="route('association.delete', $asso->id)" :message="'هتحذف '.$asso->name.'؟'" />
    @endforeach
@endpush
