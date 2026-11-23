@props(['id' => null, 'order' => null, 'pageLength' => null, 'plain' => false, 'paging' => true])
{{-- A client-side table (search, sort, pages) unless plain. Mark unsortable header cells with class "no-sort". --}}
<div class="nb-table-wrap">
    <table @if ($id) id="{{ $id }}" @endif
           {{ $attributes->class(['nb-table']) }}
           @unless ($plain) data-nb-table @endunless
           @if (! is_null($order)) data-order='@json($order)' @endif
           @if ($pageLength) data-page-length="{{ $pageLength }}" @endif
           @unless ($paging) data-paging="false" data-searching="false" @endunless>
        {{ $slot }}
    </table>
</div>
