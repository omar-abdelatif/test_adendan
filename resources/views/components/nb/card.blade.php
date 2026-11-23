@props(['title' => null, 'subtitle' => null, 'icon' => null, 'tone' => null, 'flush' => false])
<section {{ $attributes->class(['nb-card', 'nb-card--flush' => $flush, 'nb-tone-'.$tone => $tone]) }}>
    @if ($title || isset($actions))
        <header class="nb-card__head">
            @if ($icon)
                <span class="nb-card__icon" aria-hidden="true"><i data-feather="{{ $icon }}"></i></span>
            @endif
            <div>
                @if ($title)
                    <h2 class="nb-card__title">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="nb-card__sub">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="nb-card__actions">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="nb-card__body">{{ $slot }}</div>
    @isset($footer)
        <footer class="nb-card__foot">{{ $footer }}</footer>
    @endisset
</section>
