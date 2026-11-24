{{-- Month picker for a fund page: previous / next, and a month field. $route is the page's route name. --}}
@php
    $prev = $month->subMonth();
    $next = $month->addMonth();
    $hasNext = $next->lessThanOrEqualTo(now()->startOfMonth());
@endphp
<form method="GET" action="{{ route($route, $fund) }}" class="nb-monthbar" data-month-bar>
    <a class="nb-icon-btn" href="{{ route($route, [$fund, 'month' => $prev->format('Y-m')]) }}" title="الشهر اللي قبله" aria-label="الشهر اللي قبله"><i data-feather="chevron-right"></i></a>
    <input type="month" name="month" class="nb-input" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}" aria-label="الشهر">
    @if ($hasNext)
        <a class="nb-icon-btn" href="{{ route($route, [$fund, 'month' => $next->format('Y-m')]) }}" title="الشهر اللي بعده" aria-label="الشهر اللي بعده"><i data-feather="chevron-left"></i></a>
    @endif
</form>
@once
    @push('scripts')
        <script>
            $(document).on('change', '[data-month-bar] [name="month"]', function () { if (this.value) this.form.requestSubmit(); });
        </script>
    @endpush
@endonce
