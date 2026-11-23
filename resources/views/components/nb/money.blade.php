@props(['value' => 0, 'unit' => 'ج.م', 'sign' => false])
@php($number = (float) $value)
<span {{ $attributes->class(['nb-num', 'nb-nowrap']) }}>{{ $sign && $number > 0 ? '+' : '' }}{{ number_format($number) }}@if ($unit) <small class="nb-muted">{{ $unit }}</small>@endif</span>
