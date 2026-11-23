@props(['icon', 'label', 'href' => null, 'open' => null, 'tone' => null, 'type' => 'button'])
@php($classes = ['nb-icon-btn', 'nb-icon-btn--'.$tone => $tone])
@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}><i data-feather="{{ $icon }}"></i></a>
@else
    <button type="{{ $type }}" title="{{ $label }}" aria-label="{{ $label }}" @if ($open) data-nb-open="{{ $open }}" @endif {{ $attributes->class($classes) }}><i data-feather="{{ $icon }}"></i></button>
@endif
