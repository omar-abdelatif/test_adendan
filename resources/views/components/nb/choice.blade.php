@props(['label' => null, 'name', 'options' => [], 'value' => null, 'required' => false, 'span' => null])
@php
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $current = $value ?? old($key);
    $optionList = collect($options)->mapWithKeys(fn ($text, $k) => [is_int($k) && array_is_list($options) ? (string) $text : (string) $k => $text]);
@endphp
<div @class(['nb-field', 'nb-span-'.$span => $span, $attributes->get('class')]) role="radiogroup" @if ($label) aria-label="{{ $label }}" @endif>
    @if ($label)
        <span class="nb-label">{{ $label }}@if ($required)<span class="nb-req" aria-hidden="true">*</span>@endif</span>
    @endif
    <div class="nb-choice">
        @foreach ($optionList as $optionValue => $text)
            <label><input type="radio" name="{{ $name }}" value="{{ $optionValue }}" @checked(! is_null($current) && (string) $current === (string) $optionValue) @required($required) {{ $attributes->except('class') }}>{{ $text }}</label>
        @endforeach
    </div>
    @error($key)
        <span class="nb-error" role="alert">{{ $message }}</span>
    @enderror
</div>
