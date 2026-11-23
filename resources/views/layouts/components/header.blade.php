@php($user = Auth::user())
<header class="nb-top">
    <button type="button" class="nb-icon-btn nb-top__toggle" data-nb-side-toggle aria-controls="nb-side" aria-label="صغّر القائمة الجانبية"><i data-feather="menu"></i></button>
    <span class="nb-top__date">{{ now()->locale('ar')->translatedFormat('l j F Y') }}</span>
    <div class="nb-top__end">
        <x-nb.button variant="ghost" size="sm" icon="external-link" :href="route('site.index')" target="_blank" rel="noopener">زيارة الموقع</x-nb.button>
        <details class="nb-menu">
            <summary>
                <x-nb.avatar :name="$user->name" :src="$user->avatar ? asset('assets/images/users/'.$user->avatar) : null" size="sm" tone="teal" />
                <span class="nb-menu__who">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->roles->pluck('name')->implode('، ') }}</small>
                </span>
                <i data-feather="chevron-down"></i>
            </summary>
            <div class="nb-menu__list">
                <a href="{{ route('user.profile') }}"><i data-feather="user"></i>حسابي</a>
                <form action="{{ route('logout') }}" method="POST" data-nb-native>
                    @csrf
                    <button type="submit"><i data-feather="log-out"></i>تسجيل الخروج</button>
                </form>
            </div>
        </details>
    </div>
</header>
