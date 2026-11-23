@props(['id', 'title', 'icon' => null, 'size' => null, 'action' => null, 'method' => 'POST', 'files' => false, 'submit' => 'حفظ', 'danger' => false, 'autoopen' => false])
@php($verb = strtoupper($method))
<div id="{{ $id }}" hidden role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
     {{ $attributes->class(['nb-modal', 'nb-modal--'.$size => $size, 'nb-modal--danger' => $danger]) }}
     @if ($autoopen) data-nb-autoopen @endif>
    <div class="nb-modal__backdrop"></div>
    <div class="nb-modal__dialog">
        @if ($action)
            <form action="{{ $action }}" method="{{ $verb === 'GET' ? 'GET' : 'POST' }}" @if ($files) enctype="multipart/form-data" @endif>
            @if ($verb !== 'GET')
                @csrf
            @endif
            @if (! in_array($verb, ['GET', 'POST'], true))
                @method($verb)
            @endif
        @endif
        <header class="nb-modal__head">
            @if ($icon)
                <i data-feather="{{ $icon }}"></i>
            @endif
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="nb-modal__close" data-nb-close aria-label="إغلاق"><i data-feather="x"></i></button>
            <div class="nb-border" aria-hidden="true"></div>
        </header>
        <div class="nb-modal__body">{{ $slot }}</div>
        <footer class="nb-modal__foot">
            @isset($footer)
                {{ $footer }}
            @else
                <button type="button" class="nb-btn nb-btn--ghost" data-nb-close>إلغاء</button>
                @if ($action)
                    <button type="submit" @class(['nb-btn', 'nb-btn--danger' => $danger])>{{ $submit }}</button>
                @endif
            @endisset
        </footer>
        @if ($action)
            </form>
        @endif
    </div>
</div>
