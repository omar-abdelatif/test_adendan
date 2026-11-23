@props(['variant' => null, 'icon' => null, 'href' => null, 'type' => 'button', 'size' => null, 'open' => null])
@php($classes = ['nb-btn', 'nb-btn--'.$variant => $variant, 'nb-btn--'.$size => $size])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if ($icon)<i data-feather="{{ $icon }}"></i>@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" @if ($open) data-nb-open="{{ $open }}" @endif {{ $attributes->class($classes) }}>@if ($icon)<i data-feather="{{ $icon }}"></i>@endif{{ $slot }}</button>
@endif
