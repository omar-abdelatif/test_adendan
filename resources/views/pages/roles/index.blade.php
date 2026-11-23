@php
    $permissionNames = $permissions->pluck('name', 'name')->all();
    $permissionById = $permissions->pluck('name', 'id');
    $granted = fn ($role) => collect($rolePermissions[$role->id] ?? [])->map(fn ($id) => $permissionById[$id] ?? null)->filter()->values()->all();
@endphp
@extends('layouts.master')
@section('title', 'الأدوار')
@section('content')
    <x-nb.page title="الأدوار" subtitle="كل دور ليه مجموعة صلاحيات، والمستخدم بياخد صلاحيات دوره" :crumbs="['الإدارة', 'المستخدمين والصلاحيات']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="lock" :href="route('permissions.index')">الصلاحيات</x-nb.button>
            <x-nb.button icon="plus" open="#role-add">إضافة دور</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-cols" style="--cols: 3">
        @foreach ($roles as $role)
            @php($list = $granted($role))
            @php($isMaster = $role->name === 'master')
            <x-nb.card :title="$isMaster ? 'مدير النظام (master)' : $role->name" :subtitle="$isMaster ? 'كل الصلاحيات دايماً' : count($list).' من '.$permissions->count().' صلاحية'" icon="unlock" :tone="$isMaster ? null : 'gold'">
                @if ($isMaster)
                    <p class="nb-muted">الدور ده بيفتح الداشبورد كله، ومينفعش يتقفل منه حاجة.</p>
                @else
                    <div class="nb-chips">
                        @foreach (array_slice($list, 0, 8) as $name)
                            <x-nb.badge tone="teal">{{ $name }}</x-nb.badge>
                        @endforeach
                        @if (count($list) > 8)
                            <x-nb.badge>+{{ count($list) - 8 }}</x-nb.badge>
                        @endif
                        @if (! count($list))
                            <span class="nb-muted">مفيش صلاحيات لسه</span>
                        @endif
                    </div>
                @endif
                <x-slot:footer>
                    <x-nb.button variant="ghost" size="sm" icon="edit-2" :open="'#role-edit-'.$role->id">تعديل الصلاحيات</x-nb.button>
                    @unless ($isMaster)
                        <x-nb.button variant="ghost" size="sm" icon="trash-2" class="nb-out" :open="'#role-delete-'.$role->id">حذف</x-nb.button>
                    @endunless
                </x-slot:footer>
            </x-nb.card>
        @endforeach
    </div>
@endsection

@push('modals')
    <x-nb.modal id="role-add" title="إضافة دور جديد" icon="plus" size="lg" :action="route('roles.store')" submit="إضافة الدور">
        <div class="nb-stack">
            <x-nb.field label="إسم الدور" name="name" required placeholder="مثلاً: أمين الصندوق" />
            <x-nb.checks label="الصلاحيات" name="permissions[]" :options="$permissionNames" />
        </div>
    </x-nb.modal>
    @foreach ($roles as $role)
        <x-nb.modal :id="'role-edit-'.$role->id" :title="'صلاحيات '.$role->name" icon="unlock" size="lg" :action="route('roles.update')">
            <input type="hidden" name="id" value="{{ $role->id }}">
            <div class="nb-stack">
                <x-nb.field label="إسم الدور" name="name" :value="$role->name" :row="$role->id" required />
                <x-nb.checks label="الصلاحيات" name="permissions[]" :options="$permissionNames" :selected="$granted($role)" />
            </div>
        </x-nb.modal>
        @unless ($role->name === 'master')
            <x-nb.confirm :id="'role-delete-'.$role->id" :action="route('roles.destroy', $role->id)" :message="'هتحذف دور '.$role->name.'؟'" />
        @endunless
    @endforeach
@endpush
