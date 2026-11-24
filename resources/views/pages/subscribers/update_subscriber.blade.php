@php
    $m = $subscriber;
    $avatar = $m->img ? asset('assets/images/subscribers/avatar/'.$m->img) : null;
    $idCard = $m->id_img ? asset('assets/images/subscribers/id/'.$m->id_img) : null;
    $dues = $m->dues()->where('amount_remaining', '>', 0)->orderBy('item')->get();
    $owed = (int) $dues->sum('amount_remaining');
    $payments = $m->paymentTransactions()->latest('payment_date')->limit(4)->get();
    $mobile = $m->mobile_no ? '0'.ltrim((string) $m->mobile_no, '0') : null;
@endphp
@extends('layouts.master')
@section('title', $m->name)
@section('content')
    <x-nb.page :title="$m->name" subtitle="ملف العضو وتعديل البيانات" :crumbs="['كل المشتركين' => route('subscriber.all')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="arrow-right" :href="route('subscriber.all')">رجوع للمشتركين</x-nb.button>
            <x-nb.button variant="gold" icon="credit-card" :href="route('subscription.history', $m->id)">الحساب والسداد</x-nb.button>
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
                    @if ($m->job)<span><i data-feather="briefcase"></i>{{ $m->job }}</span>@endif
                </div>
            </div>
        </section>

        <div class="nb-split">
            <form action="{{ route('subscribe.update') }}" method="POST" enctype="multipart/form-data" class="nb-card">
                @csrf
                <input type="hidden" name="id" value="{{ $m->id }}">
                <div class="nb-tabs" data-nb-tabs role="tablist">
                    <button type="button" class="is-on" data-nb-tab="#tab-personal" role="tab" aria-selected="true"><i data-feather="user"></i>البيانات الشخصية</button>
                    <button type="button" data-nb-tab="#tab-study" role="tab"><i data-feather="book"></i>الدراسية</button>
                    <button type="button" data-nb-tab="#tab-work" role="tab"><i data-feather="briefcase"></i>الوظيفية</button>
                    <button type="button" data-nb-tab="#tab-membership" role="tab"><i data-feather="credit-card"></i>الإشتراك</button>
                    <button type="button" data-nb-tab="#tab-health" role="tab"><i data-feather="heart"></i>الحالة</button>
                </div>
                <div class="nb-card__body">
                    <div id="tab-personal" class="nb-form-grid" style="--cols: 3" role="tabpanel">
                        <x-nb.field label="الإسم الكامل" name="name" :value="$m->name" required span="2" />
                        <x-nb.field label="اللقب واسم الشهرة" name="nickname" :value="$m->nickname" />
                        <x-nb.field label="تاريخ الميلاد" name="birthdate" type="date" :value="$m->birthdate" />
                        <x-nb.field label="الرقم القومي" name="ssn" inputmode="numeric" maxlength="14" dir="ltr" :value="$m->ssn" required />
                        <x-nb.field label="الحالة الإجتماعية" name="martial_status" type="select" :options="['أعزب', 'متزوج', 'أرمل']" :value="$m->martial_status" />
                        <x-nb.field label="رقم المحمول" name="mobile_no" type="tel" inputmode="numeric" dir="ltr" :value="$mobile" required />
                        <x-nb.field label="رقم المحمول الاخر" name="another_mobile_no" type="tel" inputmode="numeric" dir="ltr" :value="$m->another_mobile_no" />
                        <x-nb.field label="تليفون المنزل" name="home_tel" type="tel" dir="ltr" :value="$m->home_tel" />
                        <x-nb.field label="العنوان" name="address" :value="$m->address" span="full" />
                    </div>
                    <div id="tab-study" class="nb-form-grid" role="tabpanel" hidden>
                        <x-nb.field label="المؤهل الدراسي" name="educational_qualification" :value="$m->educational_qualification" />
                        <x-nb.field label="تاريخ المؤهل" name="qualification_date" type="date" :value="$m->qualification_date" />
                    </div>
                    <div id="tab-work" class="nb-form-grid" role="tabpanel" hidden>
                        <x-nb.field label="الوظيفة" name="job" :value="$m->job" />
                        <x-nb.field label="جهة العمل" name="job_destination" :value="$m->job_destination" />
                        <x-nb.field label="عنوان العمل" name="job_address" :value="$m->job_address" />
                        <x-nb.field label="تليفون العمل" name="job_tel" type="tel" dir="ltr" :value="$m->job_tel" />
                    </div>
                    <div id="tab-membership" class="nb-form-grid" role="tabpanel" hidden>
                        <x-nb.choice label="نوع العضوية" name="membership_type" :options="['عامل', 'إنتساب']" :value="$m->membership_type" />
                        <x-nb.field label="رقم العضوية" name="member_id" type="number" :value="$m->member_id" required />
                        <x-nb.field label="الصورة الشخصية" name="img" type="file" accept="image/*" preview="#member-photo" hint="سيبها فاضية لو مش هتغيّرها" />
                        <x-nb.field label="صورة البطاقة" name="id_img" type="file" accept="image/*" preview="#member-id-card" hint="سيبها فاضية لو مش هتغيّرها" />
                    </div>
                    <div id="tab-health" class="nb-form-grid" role="tabpanel" hidden>
                        <label class="nb-check nb-span-full"><input type="checkbox" name="status" value="1" @checked((int) $m->status === 2)> المشترك متوفي</label>
                        <div class="nb-span-full" data-nb-when="status:1">
                            <x-nb.field label="اسم المقبرة" name="tomb_name" type="select" :options="$tombs->pluck('name', 'name')->all()" :value="$m->tomb_name" />
                        </div>
                        <div class="nb-alert nb-alert--gold nb-span-full"><i data-feather="info"></i><span>لما تعلّم إن المشترك متوفي، حالته بتتغير ومبيتحسبش عليه اشتراك.</span></div>
                    </div>
                </div>
                <footer class="nb-card__foot">
                    <x-nb.button variant="ghost" :href="route('subscriber.all')">إلغاء</x-nb.button>
                    <x-nb.button type="submit" icon="check">حفظ التعديلات</x-nb.button>
                </footer>
            </form>

            <div class="nb-stack">
                <x-nb.card title="عليه دلوقتي" icon="alert-circle" :tone="$owed ? 'terra' : 'green'" flush>
                    <x-slot:actions><span class="nb-strong {{ $owed ? 'nb-out' : 'nb-in' }}">{{ number_format($owed) }} ج.م</span></x-slot:actions>
                    <div class="nb-list">
                        @forelse ($dues as $due)
                            <div class="nb-list__row">
                                <div class="nb-list__main"><strong>{{ $due->transaction_type === 'إشتراك' ? 'إشتراك '.$due->item : $due->item }}</strong><span>مطلوب {{ number_format($due->total_amount) }} · مدفوع {{ number_format((int) $due->amount_paid) }}</span></div>
                                <span class="nb-out">{{ number_format((int) $due->amount_remaining) }}</span>
                            </div>
                        @empty
                            <x-nb.empty icon="check-circle" title="مفيش مستحقات" />
                        @endforelse
                    </div>
                    @if ($owed)
                        <x-slot:footer><x-nb.button class="nb-btn--block" icon="credit-card" :href="route('subscription.history', $m->id)">سداد المستحقات</x-nb.button></x-slot:footer>
                    @endif
                </x-nb.card>

                <x-nb.card title="آخر المدفوعات" icon="clock" flush>
                    <div class="nb-list">
                        @forelse ($payments as $p)
                            <div class="nb-list__row">
                                <div class="nb-list__main"><strong>{{ $p->transaction_type }} {{ $p->item }}</strong><span>{{ $p->payment_date }} · إيصال {{ $p->inv_no ?? '—' }}</span></div>
                                <span class="nb-in">{{ number_format($p->amount) }}</span>
                            </div>
                        @empty
                            <x-nb.empty icon="clock" title="لسه مفيش مدفوعات" />
                        @endforelse
                    </div>
                </x-nb.card>

                <x-nb.card title="الصور" icon="image">
                    <div class="nb-cols">
                        <figure style="margin: 0">
                            <img id="member-photo" class="nb-thumb" style="width: 100%; height: 140px" src="{{ $avatar }}" alt="الصورة الشخصية" @if (! $avatar) hidden @endif onerror="this.hidden = true">
                            @unless ($avatar)<x-nb.avatar :name="$m->name" size="xl" />@endunless
                            <figcaption class="nb-hint">الصورة الشخصية</figcaption>
                        </figure>
                        <figure style="margin: 0">
                            <img id="member-id-card" class="nb-thumb" style="width: 100%; height: 140px" src="{{ $idCard }}" alt="صورة البطاقة" @if (! $idCard) hidden @endif onerror="this.hidden = true">
                            @unless ($idCard)<x-nb.empty icon="credit-card" title="مفيش صورة بطاقة" style="padding: 14px" />@endunless
                            <figcaption class="nb-hint">صورة البطاقة</figcaption>
                        </figure>
                    </div>
                </x-nb.card>
            </div>
        </div>
    </div>
@endsection
