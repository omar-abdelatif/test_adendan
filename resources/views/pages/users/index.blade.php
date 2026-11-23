@php
    $roleNames = $roles->pluck('name', 'name')->all();
    $permissionNames = $permissions->pluck('name', 'name')->all();
    $permissionById = $permissions->pluck('name', 'id');
    $direct = fn ($user) => collect($usersPermissions[$user->id] ?? [])->map(fn ($id) => $permissionById[$id] ?? null)->filter()->values()->all();
@endphp
@extends('layouts.master')
@section('title', 'المستخدمين')
@section('content')
    <x-nb.page title="المستخدمين" subtitle="موظفين الجمعية اللي بيدخلوا الداشبورد، ودور كل واحد" :crumbs="['الإدارة', 'المستخدمين والصلاحيات']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="unlock" :href="route('roles.index')">الأدوار</x-nb.button>
            <x-nb.button icon="user-plus" open="#user-add">إضافة مستخدم</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="المستخدمين" :value="$users->count()" icon="users" />
            <x-nb.stat label="الأدوار" :value="$roles->count()" icon="unlock" tone="gold" />
            <x-nb.stat label="الصلاحيات" :value="$permissions->count()" icon="lock" tone="green" />
        </x-nb.stats>

        <x-nb.card title="كل المستخدمين" icon="users" flush>
            <x-nb.table id="users-table" :order="[[0, 'asc']]">
                <thead><tr><th>المستخدم</th><th>البريد الإلكتروني</th><th>الدور</th><th>صلاحيات إضافية</th><th class="no-sort is-actions"></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td><div class="nb-cell-main"><x-nb.avatar :name="$user->name" :src="$user->avatar ? asset('assets/images/users/'.$user->avatar) : null" size="sm" tone="teal" /><strong>{{ $user->name }}</strong></div></td>
                            <td dir="ltr" style="text-align: right">{{ $user->email }}</td>
                            <td>
                                <div class="nb-chips">
                                    @forelse ($user->roles as $role)
                                        <x-nb.badge :tone="$role->name === 'master' ? 'ink' : 'teal'">{{ $role->name === 'master' ? 'مدير النظام' : $role->name }}</x-nb.badge>
                                    @empty
                                        <span class="nb-muted">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="nb-muted">{{ count($direct($user)) ?: '—' }}</td>
                            <td class="is-actions">
                                <div class="nb-row-actions">
                                    <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#user-edit-'.$user->id" />
                                    @if ($user->id !== auth()->id())
                                        <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#user-delete-'.$user->id" />
                                    @endif
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
    <x-nb.modal id="user-add" title="إضافة مستخدم جديد" icon="user-plus" size="lg" :action="route('user.store')" submit="إضافة المستخدم">
        <div class="nb-form-grid">
            <x-nb.field label="إسم المستخدم" name="name" required autocomplete="off" />
            <x-nb.field label="البريد الإلكتروني" name="email" type="email" dir="ltr" required autocomplete="off" />
            <x-nb.field label="كلمة السر" name="password" type="password" dir="ltr" required autocomplete="new-password" hint="8 حروف على الأقل" />
            <x-nb.field label="الدور" name="role" type="select" :options="$roleNames" required />
            <x-nb.checks label="صلاحيات إضافية غير اللي في الدور (اختياري)" name="permission[]" :options="$permissionNames" class="nb-span-full" />
        </div>
    </x-nb.modal>

    @foreach ($users as $user)
        <x-nb.modal :id="'user-edit-'.$user->id" :title="'تعديل '.$user->name" icon="edit-2" size="lg" :action="route('newuser.update')">
            <input type="hidden" name="id" value="{{ $user->id }}">
            <div class="nb-form-grid">
                <x-nb.field label="إسم المستخدم" name="name" :value="$user->name" :row="$user->id" required />
                <x-nb.field label="البريد الإلكتروني" name="email" type="email" dir="ltr" :value="$user->email" :row="$user->id" required />
                <x-nb.field label="كلمة سر جديدة (اختياري)" name="password" type="password" dir="ltr" :row="$user->id" autocomplete="new-password" hint="سيبها فاضية لو مش هتغيّرها" />
                <x-nb.field label="الدور" name="role" type="select" :options="$roleNames" :value="$user->roles->first()?->name" :row="$user->id" required />
                <x-nb.checks label="صلاحيات إضافية غير اللي في الدور" name="permission[]" :options="$permissionNames" :selected="$direct($user)" class="nb-span-full" />
            </div>
        </x-nb.modal>
        @if ($user->id !== auth()->id())
            <x-nb.confirm :id="'user-delete-'.$user->id" :action="route('user.delete', $user->id)" :message="'هتحذف المستخدم '.$user->name.'؟'" />
        @endif
    @endforeach
@endpush
