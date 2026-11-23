{{-- The member file beside the members list (MembersController@preview). Every value goes through e(). --}}
@php
    use App\Modules\Members\Application\Queries\ProfileCompleteness;
    use App\Modules\Members\Domain\MemberStatus;
    use Illuminate\Support\Carbon;

    $m = $member;
    $known = fn ($v) => filled($v) && (string) $v !== '0';
    $dash = '<span class="nb-muted">—</span>';
    $text = fn ($v) => $known($v) ? e($v) : $dash;
    $ltr = fn ($v) => $known($v) ? '<bdi dir="ltr">'.e($v).'</bdi>' : $dash;
    $phone = function ($n) use ($known, $dash) {
        if (! $known($n)) {
            return $dash;
        }
        $number = '0'.ltrim((string) $n, '0');

        return '<a href="tel:'.e($number).'" dir="ltr">'.e($number).'</a>';
    };
    $birth = $known($m->birthdate) ? Carbon::parse($m->birthdate) : null;
    $deceased = (int) $m->status === MemberStatus::Deceased->value;
    $avatar = $m->img ? asset('assets/images/subscribers/avatar/'.$m->img) : null;
    $idCard = $m->id_img ? asset('assets/images/subscribers/id/'.$m->id_img) : null;
    $fileUrl = route('subscriber.details', $m->id);

    $labels = ProfileCompleteness::FIELDS + [
        'name' => 'الاسم', 'nickname' => 'اللقب', 'member_id' => 'رقم العضوية', 'status' => 'الحالة', 'membership_type' => 'نوع العضوية',
        'another_mobile_no' => 'الموبايل التاني', 'home_tel' => 'تليفون المنزل', 'job_address' => 'عنوان العمل', 'job_tel' => 'تليفون العمل',
        'qualification_date' => 'تاريخ المؤهل', 'tomb_name' => 'المدفن', 'active_sms' => 'الرسائل', 'password' => 'كلمة سر التطبيق',
    ];
    $what = function ($activity) use ($labels) {
        if ($activity->event === 'created') {
            return 'اتسجل كعضو جديد';
        }
        if ($activity->event === 'deleted') {
            return 'اتحذف من الأعضاء';
        }
        $changed = collect(array_keys((array) ($activity->properties['attributes'] ?? [])))->map(fn ($key) => $labels[$key] ?? null)->filter()->values();
        if ($changed->isEmpty()) {
            return 'اتعدلت بياناته';
        }

        return 'اتعدل: '.$changed->take(3)->implode('، ').($changed->count() > 3 ? ' و'.($changed->count() - 3).' كمان' : '');
    };
@endphp

<div class="nb-stack" data-member="{{ $m->id }}">
    <section class="nb-card nb-profile">
        <div class="nb-profile__cover" aria-hidden="true"></div>
        <div class="nb-profile__head">
            <x-nb.avatar :name="$m->name" :src="$avatar" size="xl" tone="teal" />
            <div class="nb-profile__who">
                <div class="nb-row">
                    <h2 class="nb-profile__name">{{ $m->name }}</h2>
                    @if ($known($m->nickname))
                        <x-nb.badge>{{ $m->nickname }}</x-nb.badge>
                    @endif
                </div>
                <div class="nb-profile__meta">
                    <strong class="nb-profile__id">عضوية <bdi>{{ $m->member_id }}</bdi></strong>
                    @if ($known($m->membership_type))
                        <x-nb.badge tone="teal">{{ $m->membership_type }}</x-nb.badge>
                    @endif
                    <x-nb.member-status :status="$m->status" />
                    @if ($m->active_sms)
                        <x-nb.badge tone="gold">مشترك في الرسائل</x-nb.badge>
                    @endif
                    @if ($m->created_at)
                        <span class="nb-muted">عضو من {{ $m->created_at->translatedFormat('F Y') }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="nb-profile__actions">
            <x-nb.button size="sm" icon="file-text" :href="$fileUrl">فتح الملف</x-nb.button>
            <x-nb.button variant="ghost" size="sm" icon="credit-card" :href="route('subscription.history', $m->id)">الحساب والسداد</x-nb.button>
            @unless ($deceased)
                <x-nb.button variant="ghost" size="sm" icon="gift" open="#member-donate" class="nb-profile__donate" data-member-id="{{ $m->member_id }}" data-member-name="{{ $m->name }}">تبرع من العضو</x-nb.button>
            @endunless
        </div>
        @if ($missing)
            <div class="nb-alert nb-alert--gold nb-profile__gap">
                <i data-feather="alert-circle"></i>
                <span><strong>الملف {{ $percent }}% كامل</strong> · ناقص: {{ implode('، ', $missing) }}</span>
                <a href="{{ $fileUrl }}">كمّله</a>
            </div>
        @else
            <div class="nb-alert nb-profile__gap"><i data-feather="check-circle"></i><span>الملف كامل</span></div>
        @endif
    </section>

    <div class="nb-cols" style="--cols: 2">
        <x-nb.card title="البيانات الشخصية" icon="user">
            <dl class="nb-kv">
                <dt>الرقم القومي</dt><dd>{!! $ltr($m->ssn) !!}</dd>
                <dt>تاريخ الميلاد</dt><dd>{!! $birth ? '<bdi dir="ltr">'.e($birth->format('Y-m-d')).'</bdi> · '.e($birth->age).' سنة' : $dash !!}</dd>
                <dt>الحالة الإجتماعية</dt><dd>{!! $text($m->martial_status) !!}</dd>
                <dt>الموبايل</dt><dd>{!! $phone($m->mobile_no) !!}</dd>
                <dt>الموبايل التاني</dt><dd>{!! $phone($m->another_mobile_no) !!}</dd>
                <dt>تليفون المنزل</dt><dd>{!! $ltr($m->home_tel) !!}</dd>
                <dt>العنوان</dt><dd>{!! $text($m->address) !!}</dd>
                @if ($known($m->tomb_name))
                    <dt>المدفن</dt><dd>{{ $m->tomb_name }}</dd>
                @endif
            </dl>
        </x-nb.card>
        <x-nb.card title="الشغل والدراسة" icon="briefcase">
            <dl class="nb-kv">
                <dt>الوظيفة</dt><dd>{!! $text($m->job) !!}</dd>
                <dt>جهة العمل</dt><dd>{!! $text($m->job_destination) !!}</dd>
                <dt>عنوان العمل</dt><dd>{!! $text($m->job_address) !!}</dd>
                <dt>تليفون العمل</dt><dd>{!! $ltr($m->job_tel) !!}</dd>
                <dt>المؤهل</dt><dd>{!! $text($m->educational_qualification) !!}</dd>
                <dt>تاريخ المؤهل</dt><dd>{!! $ltr($m->qualification_date) !!}</dd>
            </dl>
        </x-nb.card>
    </div>

    <div class="nb-cols" style="--cols: 2">
        <x-nb.card title="الصور" icon="image">
            <div class="nb-photo-slots">
                @foreach ([['الصورة الشخصية', $avatar], ['صورة البطاقة', $idCard]] as [$label, $src])
                    @if ($src)
                        <a class="nb-photo-slot nb-photo-slot--full" href="{{ $src }}" target="_blank" rel="noopener">
                            <img src="{{ $src }}" alt="{{ $label }}" loading="lazy">
                            <span>{{ $label }}</span>
                        </a>
                    @else
                        <a class="nb-photo-slot" href="{{ $fileUrl }}">
                            <i data-feather="upload"></i>
                            <strong>{{ $label }}</strong>
                            <span>مش مرفوعة</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </x-nb.card>
        <x-nb.card title="آخر اللي اتعمل على الملف" icon="clock" flush>
            @if ($history->isEmpty())
                <p class="nb-muted nb-profile__empty">مفيش تعديلات متسجلة على الملف ده لسه.</p>
            @else
                <ul class="nb-list nb-profile__history">
                    @foreach ($history as $activity)
                        <li class="nb-list__row">
                            <i class="nb-profile__bullet" aria-hidden="true"></i>
                            <div class="nb-list__main">
                                <strong>{{ $what($activity) }}</strong>
                                <span>{{ $activity->causer?->name ?? 'السيستم' }} · {{ $activity->created_at?->translatedFormat('j F Y') }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-nb.card>
    </div>
</div>
