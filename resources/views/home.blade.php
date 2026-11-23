@use('App\Modules\Finance\Domain\Enums\Channel')
@use('App\Modules\Finance\Domain\Enums\Direction')
@php
    $user = auth()->user();
    $greeting = now()->hour < 12 ? 'صباح الخير' : 'مساء الخير';
    $firstName = \Illuminate\Support\Str::of($user->name)->trim()->explode(' ')->first();
    $money = fn ($amount) => number_format((int) $amount);

    $actions = collect([
        ['can' => 'المشتركين', 'route' => 'subscriber.all', 'icon' => 'search', 'label' => 'بحث عن عضو وسداد'],
        ['can' => 'الإشتراك السنوي', 'route' => 'costyears.all', 'icon' => 'calendar', 'label' => 'الاشتراك السنوي'],
        ['can' => 'التقارير', 'route' => 'reports.safe', 'icon' => 'briefcase', 'label' => 'الخزنة والإيداع'],
        ['can' => 'الرسائل', 'route' => 'sms.index', 'icon' => 'message-square', 'label' => 'رسالة جماعية'],
        ['can' => 'الاخبار', 'route' => 'news.all', 'icon' => 'book-open', 'label' => 'خبر جديد'],
    ])->filter(fn ($action) => $user->can($action['can']))->values();
@endphp
@extends('layouts.master')
@section('title', 'لوحة التحكم')
@section('content')
    <div class="nb-stack">
        <section class="nb-card nb-hero" aria-label="ترحيب">
            <div class="nb-hero__text">
                <h2>{{ $greeting }} يا {{ $firstName }}</h2>
                @if ($balances)
                    <p>
                        اتحصل النهارده <strong>{{ $money($balances['today']) }} ج.م</strong>
                        في {{ $balances['todayCount'] }} {{ $balances['todayCount'] === 1 ? 'حركة' : 'حركات' }}@if ($requests && $requests['count']),
                        وفيه <a href="{{ route('reports.updateRequests') }}">{{ $requests['count'] }} طلب تحديث بيانات</a> مستني مراجعتك@endif.
                    </p>
                @else
                    <p>أهلاً بيك في لوحة إدارة جمعية أدندان الخيرية · تأسست عام 1908م</p>
                @endif
            </div>
            <div class="nb-hero__art">
                <img src="{{ asset('assets/images/nubian/village-strip.jpg') }}" alt="رسمة قرية نوبية على النيل">
            </div>
        </section>

        @if ($balances || $members)
            <x-nb.stats aria-label="أرقام رئيسية">
                @if ($balances)
                    <x-nb.stat label="رصيد الخزنة" :value="$money($balances['safe'])" unit="ج.م" icon="briefcase">
                        <x-slot:note><a href="{{ route('reports.safe') }}">حركات الخزنة</a></x-slot:note>
                    </x-nb.stat>
                    <x-nb.stat label="رصيد البنك" :value="$money($balances['bank'])" unit="ج.م" icon="home">
                        <x-slot:note><a href="{{ route('bankTransactios') }}">حركات البنك</a></x-slot:note>
                    </x-nb.stat>
                @endif
                @if ($collection)
                    <x-nb.stat :label="'تحصيل اشتراك '.($collection['year'] ?? '')" :value="$collection['percent'].'%'" icon="pie-chart" tone="gold" :progress="$collection['percent']"
                               :note="$collection['chargedMembers'] ? number_format($collection['paidMembers']).' من '.number_format($collection['chargedMembers']).' عضو سددوا بالكامل' : 'لسه ما اتصدرش اشتراك السنة دي'" />
                    <x-nb.stat label="مستحقات متأخرة" :value="$money($collection['overdue'])" unit="ج.م" icon="alert-circle" tone="terra"
                               :note="'على '.number_format($collection['overdueMembers']).' عضو من سنين فاتت'" />
                @endif
                @if ($members && ! $balances)
                    <x-nb.stat label="أعضاء مفعّلين" :value="number_format($members['active'])" icon="users" tone="green"
                               :note="'من '.number_format($members['total']).' عضو · +'.$members['newThisMonth'].' الشهر ده'" />
                @endif
            </x-nb.stats>
        @endif

        @if ($actions->isNotEmpty())
            <nav class="nb-quick" aria-label="اختصارات">
                @foreach ($actions as $action)
                    <a class="nb-quick__item" href="{{ route($action['route']) }}">
                        <i data-feather="{{ $action['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $action['label'] }}</span>
                        <span class="nb-border" aria-hidden="true"></span>
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($transactions || $requests || $user->can('الرسائل'))
            <div class="nb-split-wide">
                <div class="nb-stack">
                    @if ($monthly)
                        <x-nb.card :title="'التحصيل الشهري · سنة '.$monthly['year']" subtitle="يوليو إلى يونيو · بالجنيه" icon="bar-chart-2" flush>
                            <div class="nb-chart" role="img" aria-label="المبالغ المحصلة كل شهر من يوليو إلى يونيو">
                                @foreach ($monthly['months'] as $month)
                                    <div class="nb-chart__col">
                                        <span class="nb-chart__value">{{ $month['future'] || ! $month['amount'] ? '' : $money($month['amount']) }}</span>
                                        <span @class(['nb-chart__bar', 'is-current' => $month['current'], 'is-future' => $month['future']])
                                              style="height: {{ $month['future'] ? 4 : max(4, round($month['amount'] / $monthly['max'] * 160)) }}px"
                                              title="{{ $month['name'] }}: {{ $money($month['amount']) }} ج.م"></span>
                                        <span class="nb-chart__name">{{ mb_substr($month['name'], 0, 3) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </x-nb.card>
                    @endif

                    @if ($transactions)
                        <x-nb.card title="آخر الحركات" icon="list" flush>
                            <x-slot:actions><x-nb.button variant="ghost" size="sm" :href="route('reports.safe')">الدفتر كامل</x-nb.button></x-slot:actions>
                            @if ($transactions->isEmpty())
                                <x-nb.empty icon="list" title="لسه مفيش حركات متسجلة" />
                            @else
                                <x-nb.table plain>
                                    <thead>
                                        <tr>
                                            <th scope="col">العضو / البيان</th>
                                            <th scope="col">البند</th>
                                            <th scope="col">النوع</th>
                                            <th scope="col">التاريخ</th>
                                            <th scope="col" class="is-end">المبلغ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transactions as $transaction)
                                            @php
                                                $isOut = in_array($transaction->transaction_cat, Direction::outValues(), true);
                                                $isOnline = $transaction->transaction_method === Channel::Online->value;
                                            @endphp
                                            <tr>
                                                <td class="nb-strong">{{ $transaction->subscriber?->name ?? $transaction->transaction_type ?? '—' }}</td>
                                                <td>{{ $transaction->item }}</td>
                                                <td>
                                                    @if ($isOut)
                                                        <x-nb.badge tone="terra">سحب</x-nb.badge>
                                                    @elseif ($isOnline)
                                                        <x-nb.badge tone="teal">أونلاين</x-nb.badge>
                                                    @else
                                                        <x-nb.badge tone="green">إيداع</x-nb.badge>
                                                    @endif
                                                </td>
                                                <td class="nb-muted nb-nowrap">{{ $transaction->payment_date ? \Illuminate\Support\Carbon::parse($transaction->payment_date)->locale('ar')->translatedFormat('j F') : '—' }}</td>
                                                <td class="is-end nb-nowrap {{ $isOut ? 'nb-out' : 'nb-in' }}">{{ $isOut ? '−' : '+' }}{{ $money($transaction->amount) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-nb.table>
                            @endif
                        </x-nb.card>
                    @endif
                </div>

                <div class="nb-stack">
                    @if ($requests)
                        <x-nb.card title="طلبات تحديث البيانات" icon="refresh-cw" tone="gold" flush>
                            @if ($requests['count'])
                                <x-slot:actions><x-nb.badge tone="gold">{{ $requests['count'] }}</x-nb.badge></x-slot:actions>
                            @endif
                            <div class="nb-list">
                                @forelse ($requests['latest'] as $request)
                                    <div class="nb-list__row">
                                        <x-nb.avatar :name="$request->name" size="sm" />
                                        <div class="nb-list__main">
                                            <strong>{{ $request->name }}</strong>
                                            <span>عضوية {{ $request->member_id }} · {{ $request->created_at?->locale('ar')->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <x-nb.empty icon="check-circle" title="مفيش طلبات مستنية" />
                                @endforelse
                            </div>
                            @if ($requests['count'])
                                <x-slot:footer><x-nb.button variant="soft" :href="route('reports.updateRequests')" class="nb-btn--block">مراجعة الطلبات</x-nb.button></x-slot:footer>
                            @endif
                        </x-nb.card>
                    @endif

                    @can('الرسائل')
                        <section class="nb-card nb-art-card" aria-labelledby="nb-sms-title">
                            <img src="{{ asset('assets/images/nubian/hands.jpg') }}" alt="" loading="lazy">
                            <div class="nb-card__head">
                                <h2 class="nb-card__title" id="nb-sms-title">رصيد الرسائل</h2>
                                <div class="nb-card__actions"><a href="{{ route('sms.index') }}">خدمة الرسائل</a></div>
                            </div>
                            <div class="nb-card__body">
                                <div class="nb-stat__value" id="nb-sms-balance" aria-live="polite">…</div>
                                <div class="nb-stat__note">رسالة متبقية في الباقة</div>
                            </div>
                        </section>
                    @endcan
                </div>
            </div>
        @endif
    </div>
@endsection
@can('الرسائل')
    @push('scripts')
        <script>
            // The SMS provider is called after the page loads, so a slow or failing provider never delays the dashboard.
            (function () {
                var target = document.getElementById('nb-sms-balance');
                if (!target) return;
                fetch(@json(route('sms.balance')), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                    .then(function (data) { target.textContent = Number(data.balance || 0).toLocaleString('en-US'); })
                    .catch(function () { target.textContent = 'تعذر جلب الرصيد'; target.style.fontSize = '16px'; });
            })();
        </script>
    @endpush
@endcan
