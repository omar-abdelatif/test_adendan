@props(['icon' => null])
<div {{ $attributes->class(['nb-section-title', 'nb-span-full']) }}>@if ($icon)<i data-feather="{{ $icon }}"></i>@endif{{ $slot }}</div>
