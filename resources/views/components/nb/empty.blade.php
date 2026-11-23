@props(['icon' => 'inbox', 'title' => 'مفيش بيانات لسه', 'text' => null])
<div {{ $attributes->class(['nb-empty']) }}>
    <span class="nb-empty__icon" aria-hidden="true"><i data-feather="{{ $icon }}"></i></span>
    <strong>{{ $title }}</strong>
    @if ($text)
        <span>{{ $text }}</span>
    @endif
    {{ $slot }}
</div>
