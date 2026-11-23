<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
    <title>@yield('title') · جمعية أدندان الخيرية</title>
    @include('layouts.assets.css')
</head>
<body class="nb-guest">
    <main class="nb-login">
        <div class="nb-login__art" aria-hidden="true">
            <img src="{{ asset('assets/images/nubian/nile-landscape.jpg') }}" alt="">
            <div class="nb-login__quote">
                <strong>جمعية أدندان الخيرية</strong>
                <span>في خدمة أهل أدندان منذ 1908م</span>
            </div>
        </div>
        <div class="nb-login__panel">
            <div class="nb-login__box">
                <div class="nb-login__brand">
                    <img src="{{ asset('assets/images/nubian/logo-arch.jpg') }}" alt="شعار جمعية أدندان الخيرية">
                    <span>
                        <strong>جمعية أدندان الخيرية</strong>
                        <small>لوحة الإدارة</small>
                    </span>
                </div>
                <div class="nb-border" aria-hidden="true"></div>
                @yield('content')
            </div>
        </div>
    </main>
    <script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script>feather.replace();</script>
</body>
</html>
