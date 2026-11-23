@props(['label', 'value', 'unit' => null, 'note' => null, 'icon' => null, 'tone' => 'teal', 'main' => false, 'progress' => null])
<div {{ $attributes->class(['nb-card', 'nb-stat', 'nb-tone-'.$tone, 'is-main' => $main]) }}>
    <div class="nb-stat__head">
        <span class="nb-stat__label">{{ $label }}</span>
        @if ($icon)
            <span class="nb-stat__icon" aria-hidden="true"><i data-feather="{{ $icon }}"></i></span>
        @endif
    </div>
    <div class="nb-stat__value">{{ $value }}@if ($unit)<small>{{ $unit }}</small>@endif</div>
    @if (! is_null($progress))
        <div class="nb-progress" role="progressbar" aria-valuenow="{{ (int) $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ max(0, min(100, (int) $progress)) }}%"></span></div>
    @else
        <div class="nb-stat__rule" aria-hidden="true"></div>
    @endif
    @if ($note)
        <div class="nb-stat__note">{{ $note }}</div>
    @endif
</div>
