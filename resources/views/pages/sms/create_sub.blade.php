@php
    $phone = fn ($n) => $n ? '0'.ltrim((string) $n, '0') : '—';
    $active = $subscribed->where('active_sms', 1);
    $expiringSoon = $active->filter(fn ($s) => $s->subscription_expiry_date && $s->subscription_expiry_date <= now()->addDays(30)->toDateString());
@endphp
@extends('layouts.master')
@section('title', 'مشتركي الرسائل')
@section('content')
    <x-nb.page title="مشتركي الرسائل" subtitle="الأعضاء وغير الأعضاء المشتركين في رسائل العزاء والمناسبات" :crumbs="['التواصل', 'الرسائل' => route('sms.index')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="send" :href="route('sms.index')">إرسال رسالة</x-nb.button>
            <x-nb.button icon="user-plus" open="#sms-non-member">اشتراك رقم من بره الجمعية</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="اشتراك مفعل" :value="number_format($active->count())" icon="check-circle" tone="green" />
            <x-nb.stat label="منتهي" :value="number_format($subscribed->count() - $active->count())" icon="clock" tone="terra" />
            <x-nb.stat label="بيخلص خلال 30 يوم" :value="number_format($expiringSoon->count())" icon="alert-triangle" tone="gold" />
            <x-nb.stat label="غير أعضاء" :value="number_format($subscribed->whereNull('member_id')->count())" icon="user" />
        </x-nb.stats>

        <section class="nb-card">
            <div class="nb-tabs" data-nb-tabs role="tablist">
                <button type="button" class="is-on" data-nb-tab="#tab-subscribed" role="tab"><i data-feather="smartphone"></i>المشتركين <em>{{ $subscribed->count() }}</em></button>
                <button type="button" data-nb-tab="#tab-members" role="tab"><i data-feather="users"></i>اشتراك عضو <em>{{ $subscribers->count() }}</em></button>
            </div>

            <div id="tab-subscribed" role="tabpanel">
                <x-nb.table id="sms-subscribed" :order="[[3, 'asc']]">
                    <thead><tr><th>رقم العضوية</th><th>رقم المحمول</th><th>الحالة</th><th>نهاية الاشتراك</th><th class="no-sort is-actions"></th></tr></thead>
                    <tbody>
                        @foreach ($subscribed as $subscriber)
                            <tr>
                                <td>{!! $subscriber->member_id ? '<span class="nb-num">'.e($subscriber->member_id).'</span>' : '<span class="nb-badge">غير عضو</span>' !!}</td>
                                <td class="nb-num" dir="ltr">{{ $phone($subscriber->mobile_no) }}</td>
                                <td>{!! (int) $subscriber->active_sms === 1 ? '<span class="nb-badge nb-badge--green nb-badge--dot">مفعل</span>' : '<span class="nb-badge nb-badge--terra nb-badge--dot">منتهي</span>' !!}</td>
                                <td class="nb-num">{{ $subscriber->subscription_expiry_date ?? '—' }}</td>
                                <td class="is-actions">
                                    <div class="nb-row-actions">
                                        @if ((int) $subscriber->active_sms === 0)
                                            <form action="{{ route('sms.renew', ['id' => $subscriber->id]) }}" method="POST" data-nb-ajax data-nb-reload>
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="member_id" value="{{ $subscriber->member_id }}">
                                                <x-nb.button type="submit" size="sm" variant="gold" icon="refresh-cw">تجديد</x-nb.button>
                                            </form>
                                        @endif
                                        @can('sms-delete-subscriber')
                                            <x-nb.icon-button icon="trash-2" label="حذف" tone="danger" :open="'#sms-delete-'.$subscriber->id" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            </div>

            <div id="tab-members" role="tabpanel" hidden>
                <x-nb.table id="sms-members" :order="[[0, 'asc']]">
                    <thead><tr><th>رقم العضوية</th><th>العضو</th><th>رقم المحمول</th><th class="no-sort is-actions"></th></tr></thead>
                    <tbody>
                        @foreach ($subscribers as $subscriber)
                            <tr>
                                <td class="nb-num">{{ $subscriber->member_id }}</td>
                                <td class="nb-strong">{{ $subscriber->name }}</td>
                                <td class="nb-num" dir="ltr">{{ $phone($subscriber->mobile_no) }}</td>
                                <td class="is-actions">
                                    <x-nb.button size="sm" variant="soft" icon="plus" open="#sms-member" data-member-id="{{ $subscriber->member_id }}" data-member-name="{{ $subscriber->name }}" data-mobile="{{ $subscriber->mobile_no }}">اشتراك</x-nb.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-nb.table>
            </div>
        </section>
    </div>
@endsection

@push('modals')
    <x-nb.modal id="sms-non-member" title="اشتراك رقم من بره الجمعية" icon="user-plus" size="sm" :action="route('sms.storeSubscriber')" submit="اشتراك">
        <x-nb.field label="رقم المحمول" name="mobile_no" type="tel" inputmode="numeric" dir="ltr" required />
    </x-nb.modal>
    <x-nb.modal id="sms-member" title="اشتراك عضو في الرسائل" icon="smartphone" size="sm" :action="route('sms.storeSubscriber')" submit="تأكيد الاشتراك">
        <div class="nb-form-grid" style="--cols: 1">
            <x-nb.field label="العضو" name="member_name_display" id="sms-member-name" readonly disabled />
            <x-nb.field label="رقم العضوية" name="member_id" type="number" id="sms-member-id" readonly required />
            <x-nb.field label="رقم المحمول" name="mobile_no" id="sms-member-mobile" dir="ltr" readonly required />
        </div>
    </x-nb.modal>
    @can('sms-delete-subscriber')
        @foreach ($subscribed as $subscriber)
            <x-nb.confirm :id="'sms-delete-'.$subscriber->id" :action="route('sms.deleteSubscriber', ['id' => $subscriber->id])" :message="'هتحذف اشتراك '.$phone($subscriber->mobile_no).'؟'" />
        @endforeach
    @endcan
@endpush

@push('scripts')
    <script>
        $(document).on('click', '[data-nb-open="#sms-member"]', function () {
            $('#sms-member-id').val($(this).data('memberId'));
            $('#sms-member-name').val($(this).data('memberName'));
            $('#sms-member-mobile').val($(this).data('mobile'));
        });
    </script>
@endpush
