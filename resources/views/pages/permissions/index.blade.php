@extends('layouts.master')
@section('title', 'الصلاحيات')
@section('content')
    <x-nb.page title="الصلاحيات" subtitle="كل صلاحية بتفتح جزء من الداشبورد، وبتتجمع في أدوار" :crumbs="['الإدارة', 'المستخدمين والصلاحيات']">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="unlock" :href="route('roles.index')">الأدوار</x-nb.button>
            <x-nb.button icon="plus" open="#permission-add">إضافة صلاحية</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <x-nb.card title="كل الصلاحيات" :subtitle="$permissions->count().' صلاحية'" icon="lock" flush>
        <x-nb.table id="permissions-table" :order="[[0, 'asc']]" :page-length="25">
            <thead>
                <tr>
                    <th>الصلاحية</th>
                    <th>الأدوار اللي عندها</th>
                    <th class="no-sort is-actions"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr>
                        <td><div class="nb-cell-main"><span class="nb-card__icon" aria-hidden="true"><i data-feather="lock"></i></span><strong>{{ $permission->name }}</strong></div></td>
                        <td>
                            <div class="nb-chips">
                                @forelse ($permission->roles ?? [] as $role)
                                    <x-nb.badge tone="teal">{{ $role->name }}</x-nb.badge>
                                @empty
                                    <span class="nb-muted">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="is-actions">
                            <div class="nb-row-actions">
                                <x-nb.icon-button icon="edit-2" label="تعديل" :open="'#permission-edit-'.$permission->id" />
                                <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#permission-delete-'.$permission->id" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-nb.table>
    </x-nb.card>
@endsection

@push('modals')
    <x-nb.modal id="permission-add" title="إضافة صلاحية" icon="plus" size="sm" :action="route('permissions.store')" submit="إضافة">
        <x-nb.field label="إسم الصلاحية" name="name" required />
    </x-nb.modal>
    @foreach ($permissions as $permission)
        <x-nb.modal :id="'permission-edit-'.$permission->id" :title="'تعديل '.$permission->name" icon="edit-2" size="sm" :action="route('permissions.update')">
            <input type="hidden" name="id" value="{{ $permission->id }}">
            <x-nb.field label="إسم الصلاحية" name="name" :value="$permission->name" :row="$permission->id" required />
            <div class="nb-alert nb-alert--gold" style="margin-top: 12px"><i data-feather="alert-triangle"></i><span>الكود بيستخدم أسماء الصلاحيات؛ تغيير الاسم ممكن يقفل صفحات.</span></div>
        </x-nb.modal>
        <x-nb.confirm :id="'permission-delete-'.$permission->id" :action="route('permissions.destroy', $permission->id)" :message="'هتحذف صلاحية '.$permission->name.'؟'" />
    @endforeach
@endpush
