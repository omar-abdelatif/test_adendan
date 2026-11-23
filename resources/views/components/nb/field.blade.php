@props([
    'label' => null, 'name', 'type' => 'text', 'value' => null, 'options' => [], 'placeholder' => null,
    'required' => false, 'hint' => null, 'span' => null, 'id' => null, 'multiple' => false,
    'accept' => null, 'rows' => 4, 'empty' => null, 'preview' => null, 'row' => null,
])
@php
    // Old input wins only when no value was given, so per-row edit forms keep their own record.
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $id ??= 'f_'.preg_replace('/[^a-z0-9_]/i', '_', $key).'_'.($row ?? \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)));
    $current = $value ?? old($key);
    $invalid = $errors->has($key);
    $optionList = collect($options)->mapWithKeys(fn ($text, $k) => [is_int($k) && array_is_list($options) ? (string) $text : (string) $k => $text]);
    $extra = $attributes->except(['class']);
@endphp
<div @class(['nb-field', 'nb-span-'.$span => $span, $attributes->get('class')])>
    @if ($label)
        <label class="nb-label" for="{{ $id }}">{{ $label }}@if ($required)<span class="nb-req" aria-hidden="true">*</span>@endif</label>
    @endif
    @if ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @class(['nb-input', 'is-invalid' => $invalid]) @required($required) @if ($multiple) multiple @endif {{ $extra }}>
            @if ($empty !== false)
                <option value="">{{ $empty ?? '— اختار —' }}</option>
            @endif
            @foreach ($optionList as $optionValue => $text)
                <option value="{{ $optionValue }}" @selected(! is_null($current) && (string) $current === (string) $optionValue)>{{ $text }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}" @class(['nb-input', 'is-invalid' => $invalid]) @required($required) {{ $extra }}>{{ $current }}</textarea>
    @elseif ($type === 'file')
        <label @class(['nb-file', 'is-invalid' => $invalid])>
            <i data-feather="upload"></i>
            <span class="nb-file__name" data-empty="{{ $placeholder ?? 'اختار ملف' }}">{{ $placeholder ?? 'اختار ملف' }}</span>
            <input type="file" id="{{ $id }}" name="{{ $name }}" @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif @if ($preview) data-preview="{{ $preview }}" @endif @required($required) {{ $extra }}>
        </label>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $current }}" placeholder="{{ $placeholder }}"
               @class(['nb-input', 'is-invalid' => $invalid]) @required($required) {{ $extra }}>
    @endif
    @if ($hint)
        <span class="nb-hint">{{ $hint }}</span>
    @endif
    @error($key)
        <span class="nb-error" role="alert">{{ $message }}</span>
    @enderror
</div>
