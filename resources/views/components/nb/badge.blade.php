@props(['tone' => null, 'dot' => false])
<span {{ $attributes->class(['nb-badge', 'nb-badge--'.$tone => $tone, 'nb-badge--dot' => $dot]) }}>{{ $slot }}</span>
