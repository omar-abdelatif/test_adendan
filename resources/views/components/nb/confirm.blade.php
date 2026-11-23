@props(['id', 'action', 'message', 'title' => 'تأكيد الحذف', 'method' => 'DELETE', 'submit' => 'أيوه، احذف'])
<x-nb.modal :id="$id" :title="$title" icon="trash-2" size="sm" :action="$action" :method="$method" :submit="$submit" danger>
    <div class="nb-confirm">
        <span class="nb-confirm__icon" aria-hidden="true"><i data-feather="alert-triangle"></i></span>
        <strong>{{ $message }}</strong>
        <span class="nb-muted">الحذف مينفعش يترجع فيه.</span>
        {{ $slot }}
    </div>
</x-nb.modal>
