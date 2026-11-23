@props(['status' => null])
@php
    $case = is_null($status) ? null : \App\Modules\Members\Domain\MemberStatus::tryFrom((int) $status);
    $tone = match ($case) {
        \App\Modules\Members\Domain\MemberStatus::Active => 'green',
        \App\Modules\Members\Domain\MemberStatus::Suspended => 'gold',
        \App\Modules\Members\Domain\MemberStatus::Deceased => 'ink',
        default => null,
    };
@endphp
<x-nb.badge :tone="$tone" dot {{ $attributes }}>{{ $case?->label() ?? 'غير محدد' }}</x-nb.badge>
