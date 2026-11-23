@props(['label' => null, 'name', 'options' => [], 'selected' => [], 'cols' => 3])
@php
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $chosen = collect(old($key, $selected))->map(fn ($v) => (string) $v)->all();
    $optionList = collect($options)->mapWithKeys(fn ($text, $k) => [is_int($k) && array_is_list($options) ? (string) $text : (string) $k => $text]);
@endphp
<div @class(['nb-field', $attributes->get('class')])>
    @if ($label)
        <span class="nb-label">{{ $label }}</span>
    @endif
    <div class="nb-checks" style="--cols: {{ $cols }}">
        @foreach ($optionList as $optionValue => $text)
            <label class="nb-check"><input type="checkbox" name="{{ $name }}" value="{{ $optionValue }}" @checked(in_array((string) $optionValue, $chosen, true))>{{ $text }}</label>
        @endforeach
    </div>
    @error($key)
        <span class="nb-error" role="alert">{{ $message }}</span>
    @enderror
</div>
