@php
    $m = $subscriber;
    $avatar = $m->img ? asset('assets/images/subscribers/avatar/'.$m->img) : null;
    $dues = $subDue->map(fn ($d) => [$d, 'إشتراك'])->concat($donDue->map(fn ($d) => [$d, 'تبرعات']));
    $open = $dues->filter(fn ($row) => (int) $row[0]->amount_remaining > 0);
    $paidTotal = (int) $subscriptions->sum('amount');
    $mobile = $m->mobile_no ? '0'.ltrim((string) $m->mobile_no, '0') : null;
@endphp
@extends('layouts.master')
@section('title', 'حساب '.$m->name)
@section('content')
    <x-nb.page :title="'حساب '.$m->name" subtitle="المستحقات والمدفوعات وسداد المديونية" :crumbs="['كل المشتركين' => route('subscriber.all')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="user" :href="route('subscriber.details', $m->id)">ملف العضو</x-nb.button>
            <x-nb.button variant="ghost" icon="printer" onclick="window.print()">طباعة كشف الحساب</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <section class="nb-card nb-member">
            <x-nb.avatar :name="$m->name" :src="$avatar" size="lg" tone="teal" />
            <div>
                <div class="nb-member__name"><h1>{{ $m->name }}</h1><x-nb.member-status :status="$m->status" /></div>
                <div class="nb-member__meta">
                    <span><i data-feather="hash"></i>عضوية {{ $m->member_id }}</span>
                    @if ($m->membership_type)<span><i data-feather="user-check"></i>{{ $m->membership_type }}</span>@endif
                    @if ($mobile)<span dir="ltr"><i data-feather="phone"></i>{{ $mobile }}</span>@endif
                    @if ($m->address)<span><i data-feather="map-pin"></i>{{ $m->address }}</span>@endif
                </div>
            </div>
        </section>

        <x-nb.stats>
            <x-nb.stat label="مستحقات الإشتراك" :value="number_format($totalSubscriptionAmount)" unit="ج.م" icon="calendar" :tone="$totalSubscriptionAmount ? 'terra' : 'green'" :note="$subDue->where('amount_remaining', '>', 0)->count().' سنة عليها باقي'" />
            <x-nb.stat label="مستحقات التبرعات" :value="number_format($totalDonationAmount)" unit="ج.م" icon="heart" :tone="$totalDonationAmount ? 'gold' : 'green'" />
            <x-nb.stat label="إجمالي اللي اتدفع" :value="number_format($paidTotal)" unit="ج.م" icon="check-circle" tone="green" :note="$subscriptions->count().' عملية'" />
        </x-nb.stats>

        <section class="nb-card">
            <div class="nb-tabs" data-nb-tabs role="tablist">
                <button type="button" class="is-on" data-nb-tab="#tab-dues" role="tab" aria-selected="true"><i data-feather="list"></i>المستحقات <em>{{ $open->count() }}</em></button>
                <button type="button" data-nb-tab="#tab-history" role="tab"><i data-feather="clock"></i>المدفوعات السابقة <em>{{ $subscriptions->count() }}</em></button>
            </div>

            <div id="tab-dues" role="tabpanel">
                @if ($dues->isEmpty())
                    <x-nb.empty icon="check-circle" title="مفيش مستحقات على العضو" />
                @else
                    <x-nb.table id="dues-table" :order="[[1, 'desc']]">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>البند</th>
                                <th class="is-end">المطلوب</th>
                                <th class="is-end">المدفوع</th>
                                <th class="is-end">الباقي</th>
                                <th class="no-sort is-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dues as [$due, $kind])
                                @php($remaining = (int) $due->amount_remaining)
                                <tr>
                                    <td><x-nb.badge :tone="$kind === 'إشتراك' ? 'teal' : 'gold'">{{ $kind }}</x-nb.badge></td>
                                    <td class="nb-strong">{{ $due->item }}</td>
                                    <td class="is-end nb-num">{{ number_format($due->total_amount) }}</td>
                                    <td class="is-end nb-num">{{ number_format((int) $due->amount_paid) }}</td>
                                    <td class="is-end">
                                        @if ($remaining > 0)
                                            <span class="nb-out">{{ number_format($remaining) }}</span>
                                        @else
                                            <x-nb.badge tone="green" dot>مسدد</x-nb.badge>
                                        @endif
                                    </td>
                                    <td class="is-actions">
                                        @if ($remaining > 0)
                                            <x-nb.button size="sm" variant="gold" icon="credit-card" :open="'#pay-'.$due->id">سداد</x-nb.button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-nb.table>
                @endif
            </div>

            <div id="tab-history" role="tabpanel" hidden>
                @if ($subscriptions->isEmpty())
                    <x-nb.empty icon="clock" title="لسه مفيش مدفوعات" />
                @else
                    <x-nb.table id="history-table" :order="[[4, 'desc']]">
                        <thead>
                            <tr>
                                <th>نوع المعاملة</th>
                                <th>البند</th>
                                <th class="is-end">المبلغ</th>
                                <th>نوع الدفع</th>
                                <th>تاريخ الدفع</th>
                                <th>رقم الإيصال</th>
                                <th>طريقة الدفع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscriptions as $item)
                                <tr>
                                    <td><x-nb.badge tone="teal">{{ $item->transaction_type }}</x-nb.badge></td>
                                    <td class="nb-strong">{{ $item->item }}</td>
                                    <td class="is-end nb-in">{{ $item->amount ? number_format($item->amount) : '—' }}</td>
                                    <td>{{ $item->payment_cat }}</td>
                                    <td class="nb-num nb-nowrap">{{ $item->payment_date }}</td>
                                    <td class="nb-num">{{ $item->inv_no ?? '—' }}</td>
                                    <td>{{ $item->payment_method }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-nb.table>
                @endif
            </div>
        </section>
    </div>
@endsection

@push('modals')
    @foreach ($open as [$due, $kind])
        @php($remaining = (int) $due->amount_remaining)
        <x-nb.modal :id="'pay-'.$due->id" :title="'تسديد '.($kind === 'إشتراك' ? 'إشتراك '.$due->item : $due->item).' · '.$m->name" icon="credit-card" size="lg" :action="route('subscription.pay')" submit="تأكيد السداد">
            <input type="hidden" name="id" value="{{ $due->id }}">
            <div class="nb-split" style="grid-template-columns: minmax(0, 1fr) 280px">
                <div class="nb-form-grid">
                    <x-nb.field label="رقم العضوية" name="member_id" type="number" :value="$due->member_id" :row="$due->id" readonly />
                    <x-nb.field label="البند" name="item" :value="$due->item" :row="$due->id" readonly />
                    <x-nb.field label="المبلغ المدفوع" name="amount_paid" type="number" min="1" :max="$remaining" :value="$remaining" :row="$due->id" required :hint="'الباقي '.number_format($remaining).' ج.م'" />
                    <x-nb.field label="رقم الإيصال" name="inv_no" inputmode="numeric" maxlength="5" :row="$due->id" required />
                    <x-nb.field label="تاريخ الدفع" name="payment_date" type="date" :value="now()->toDateString()" :row="$due->id" />
                    <x-nb.choice label="طريقة الدفع" name="payment_method" :options="['كاش', 'فيزا', 'محفظة']" value="كاش" />
                </div>
                <div class="nb-receipt" aria-label="ملخص السداد">
                    <div class="nb-receipt__head">
                        <img src="{{ asset('assets/images/nubian/logo-arch.jpg') }}" alt="">
                        <div class="nb-strong">جمعية أدندان الخيرية</div>
                        <small class="nb-muted">ملخص قبل التأكيد</small>
                    </div>
                    <div class="nb-receipt__line"><span>العضو</span><span>{{ $m->name }}</span></div>
                    <div class="nb-receipt__line"><span>رقم العضوية</span><span>{{ $m->member_id }}</span></div>
                    <div class="nb-receipt__line"><span>البند</span><span>{{ $kind }} {{ $due->item }}</span></div>
                    <div class="nb-receipt__line"><span>المطلوب</span><span>{{ number_format($due->total_amount) }}</span></div>
                    <div class="nb-receipt__line"><span>اتدفع قبل كده</span><span>{{ number_format((int) $due->amount_paid) }}</span></div>
                    <div class="nb-receipt__total"><span>الباقي</span><span>{{ number_format($remaining) }} ج.م</span></div>
                </div>
            </div>
        </x-nb.modal>
    @endforeach
@endpush
