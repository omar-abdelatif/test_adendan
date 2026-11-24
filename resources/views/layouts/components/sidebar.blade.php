@php
    $user = Auth::user();
    $on = fn (string ...$routes) => request()->routeIs(...$routes) ? 'is-active' : '';
    $reports = [
        'reports.safe' => 'الخزنة',
        'bankTransactios' => 'البنك',
        'reports.subscriptions' => 'الإشتراكات',
        'reports.search' => 'متأخرات الإشتراك',
        'reports.innerDonations' => 'التبرعات الداخلية',
        'reports.donations' => 'التبرعات الخارجية',
        'reports.updateRequests' => 'طلبات التحديث',
        'reports.subscribersData' => 'بيانات للمراجعة',
        'reports.incompete' => 'البيانات الناقصة',
        'reports.associates' => 'المنتسبين',
        'reports.location' => 'السكن',
        'reports.age' => 'السن',
        'reports.jobs' => 'الوظائف',
    ];
    $staff = ['user.index' => 'المستخدمين', 'roles.index' => 'الأدوار', 'permissions.index' => 'الصلاحيات'];
@endphp
<aside class="nb-side" id="nb-side" aria-label="القائمة الرئيسية">
    <a href="{{ route('home') }}" class="nb-side__brand">
        <img src="{{ asset('assets/images/nubian/logo-arch.jpg') }}" alt="">
        <span>
            <strong>جمعية أدندان الخيرية</strong>
            <small>لوحة الإدارة</small>
        </span>
    </a>
    <div class="nb-border nb-side__border" aria-hidden="true"></div>
    <nav class="nb-side__nav">
        @can('لوحة التحكم')
            <a class="nb-side__link {{ $on('home') }}" href="{{ route('home') }}"><i data-feather="home"></i>لوحة التحكم</a>
        @endcan

        @canany(['المشتركين', 'الإشتراك السنوي', 'التبرعات الخارجية', 'النثريات', 'التقارير'])
            <div class="nb-side__group">الأعضاء والمالية</div>
        @endcanany
        @can('المشتركين')
            <a class="nb-side__link {{ $on('subscriber.*', 'subscription.*') }}" href="{{ route('subscriber.all') }}"><i data-feather="users"></i>المشتركين</a>
        @endcan
        @can('الإشتراك السنوي')
            <a class="nb-side__link {{ $on('costyears.*') }}" href="{{ route('costyears.all') }}"><i data-feather="calendar"></i>الإشتراك السنوي</a>
        @endcan
        @can('التبرعات الخارجية')
            <a class="nb-side__link {{ $on('donators.*', 'outerDonations.*', 'outer_donations.*') }}" href="{{ route('donators.all') }}"><i data-feather="gift"></i>المتبرعين</a>
        @endcan
        @can('النثريات')
            <a class="nb-side__link {{ $on('miscellaneous.*') }}" href="{{ route('miscellaneous.all') }}"><i data-feather="file-text"></i>النثريات</a>
        @endcan
        @can('التقارير')
            <details @if (request()->routeIs('reports.*', 'bankTransactios')) open @endif>
                <summary class="nb-side__link {{ $on('reports.*', 'bankTransactios') }}"><i data-feather="bar-chart-2"></i>التقارير<i data-feather="chevron-down" class="nb-side__chev"></i></summary>
                <div class="nb-side__sub">
                    @foreach ($reports as $route => $label)
                        <a href="{{ route($route) }}" class="{{ $on($route) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </details>
        @endcan

        @canany(['الاخبار', 'الرسائل', 'الافراح'])
            <div class="nb-side__group">التواصل</div>
        @endcanany
        @can('الرسائل')
            <details @if (request()->routeIs('sms.*')) open @endif>
                <summary class="nb-side__link {{ $on('sms.*') }}"><i data-feather="message-square"></i>الرسائل<i data-feather="chevron-down" class="nb-side__chev"></i></summary>
                <div class="nb-side__sub">
                    <a href="{{ route('sms.index') }}" class="{{ $on('sms.index') }}">إرسال رسالة</a>
                    <a href="{{ route('sms.createNew') }}" class="{{ $on('sms.createNew') }}">مشتركي الرسائل</a>
                </div>
            </details>
        @endcan
        @can('الاخبار')
            <a class="nb-side__link {{ $on('news.*', 'show.thumbs') }}" href="{{ route('news.all') }}"><i data-feather="book-open"></i>الأخبار</a>
        @endcan
        @can('الافراح')
            <a class="nb-side__link {{ $on('weddings.*') }}" href="{{ route('weddings.all') }}"><i data-feather="heart"></i>الأفراح</a>
        @endcan

        @php($fundPage = fn (string $fund) => request()->routeIs('funds.*') && (request()->route('fund')?->value ?? request()->route('fund')) === $fund)
        @canany(['الكفالة', 'خزنة الكفالة'])
            <div class="nb-side__group">الكفالة</div>
        @endcanany
        @can('الكفالة')
            <a class="nb-side__link {{ $on('sponsorship.cases.*') }}" href="{{ route('sponsorship.cases.index') }}"><i data-feather="users"></i>الحالات</a>
            <a class="nb-side__link {{ $fundPage('sponsorship') && ! request()->routeIs('funds.accounts*') ? 'is-active' : '' }}" href="{{ route('funds.donors', 'sponsorship') }}"><i data-feather="gift"></i>المتبرعين</a>
        @endcan
        @can('خزنة الكفالة')
            <a class="nb-side__link {{ $fundPage('sponsorship') && request()->routeIs('funds.accounts*') ? 'is-active' : '' }}" href="{{ route('funds.accounts', 'sponsorship') }}"><i data-feather="archive"></i>الخزنة والبنك</a>
        @endcan
        @can('الكفالة')
            <a class="nb-side__link {{ $on('sponsorship.reports*') }}" href="{{ route('sponsorship.reports') }}"><i data-feather="bar-chart-2"></i>تقرير الكفالة</a>
        @endcan

        @canany(['المقابر', 'خزنة المقابر الجديدة'])
            <div class="nb-side__group">المقابر</div>
        @endcanany
        @can('المقابر')
            <a class="nb-side__link {{ $on('cemeteries.index', 'cemeteries.tombs.*', 'tomb.all') }}" href="{{ route('cemeteries.index') }}"><i data-feather="map"></i>المقابر والعيون</a>
            <a class="nb-side__link {{ $on('cemeteries.burials.*') }}" href="{{ route('cemeteries.burials.index') }}"><i data-feather="moon"></i>المتوفيين</a>
            <a class="nb-side__link {{ $fundPage('new_tombs') && ! request()->routeIs('funds.accounts*') ? 'is-active' : '' }}" href="{{ route('funds.donors', 'new_tombs') }}"><i data-feather="gift"></i>تبرعات المقابر الجديدة</a>
        @endcan
        @can('خزنة المقابر الجديدة')
            <a class="nb-side__link {{ $fundPage('new_tombs') && request()->routeIs('funds.accounts*') ? 'is-active' : '' }}" href="{{ route('funds.accounts', 'new_tombs') }}"><i data-feather="archive"></i>خزنة المقابر الجديدة</a>
        @endcan

        @canany(['مجلس الادارة', 'اللجان', 'الحرفيين'])
            <div class="nb-side__group">الجمعية والدليل</div>
        @endcanany
        @can('مجلس الادارة')
            <a class="nb-side__link {{ $on('board.*') }}" href="{{ route('board.all') }}"><i data-feather="award"></i>مجلس الإدارة</a>
        @endcan
        @can('اللجان')
            <a class="nb-side__link {{ $on('association.*') }}" href="{{ route('association.all') }}"><i data-feather="layers"></i>اللجان</a>
        @endcan
        @can('الحرفيين')
            <a class="nb-side__link {{ $on('workers.*') }}" href="{{ route('workers.all') }}"><i data-feather="briefcase"></i>الحرفيين</a>
        @endcan

        @if ($user->hasRole('master'))
            <div class="nb-side__group">الإدارة</div>
            <details @if (request()->routeIs(...array_keys($staff))) open @endif>
                <summary class="nb-side__link {{ $on(...array_keys($staff)) }}"><i data-feather="shield"></i>المستخدمين والصلاحيات<i data-feather="chevron-down" class="nb-side__chev"></i></summary>
                <div class="nb-side__sub">
                    @foreach ($staff as $route => $label)
                        <a href="{{ route($route) }}" class="{{ $on($route) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </details>
            <a class="nb-side__link {{ $on('activity.*') }}" href="{{ route('activity.index') }}"><i data-feather="activity"></i>سجل النشاطات</a>
        @endif
    </nav>
    <div class="nb-side__foot">في خدمة أهل أدندان منذ 1908م</div>
</aside>
