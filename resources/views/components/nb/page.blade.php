@props(['title', 'subtitle' => null, 'crumbs' => []])
{{-- Page title with breadcrumbs. $crumbs: ['label' => url, ...] or plain labels. --}}
<div class="nb-page-head">
    <div>
        <nav class="nb-crumbs" aria-label="المسار">
            <a href="{{ route('home') }}" aria-label="الرئيسية"><i data-feather="home"></i></a>
            @foreach ($crumbs as $label => $url)
                <span aria-hidden="true">›</span>
                @if (is_string($label))
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span>{{ $url }}</span>
                @endif
            @endforeach
            <span aria-hidden="true">›</span>
            <span aria-current="page">{{ $title }}</span>
        </nav>
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p class="nb-page-head__sub">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="nb-page-head__actions">{{ $actions }}</div>
    @endisset
</div>
