@props(['name' => '', 'src' => null, 'size' => null, 'tone' => null])
@php
    $initials = collect(preg_split('/\s+/u', trim(preg_replace('/^(م|د|أ)\.\s*/u', '', (string) $name))))->filter()->take(2)
        ->map(fn ($word) => mb_substr($word, 0, 1))->implode(' ');
@endphp
{{-- Initials underneath; the photo covers them and removes itself if the file is missing. --}}
<span {{ $attributes->class(['nb-avatar', 'nb-avatar--'.$size => $size, 'nb-avatar--'.$tone => $tone]) }} aria-hidden="true">
    {{ $initials ?: '؟' }}
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy" onerror="this.remove()">
    @endif
</span>
