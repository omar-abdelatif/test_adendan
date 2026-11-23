<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="author" content="جمعية أدندان الخيرية">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
        <title>@yield('title') · جمعية أدندان الخيرية</title>
        {{-- the icon-only sidebar is remembered per browser; set before paint so it doesn't jump --}}
        <script>try { if (localStorage.getItem('nb-side-mini') === '1') document.documentElement.classList.add('nb-side-mini'); } catch (e) {}</script>
        @include('layouts.assets.css')
        @stack('styles')
    </head>
    <body class="nb">
        <div class="nb-app">
            @include('layouts.components.sidebar')
            <div class="nb-scrim" aria-hidden="true"></div>
            <div class="nb-main">
                @include('layouts.components.header')
                <main class="nb-content" id="main">
                    @yield('content')
                </main>
                @include('layouts.components.footer')
            </div>
        </div>

        {{-- page modals sit outside the layout so they cover everything --}}
        @stack('modals')

        <div class="nb-toasts" aria-live="polite">
            @if (session()->has('message'))
                @php($tone = ['success' => 'success', 'error' => 'error', 'warning' => 'warning'][session('alert-type')] ?? 'info')
                <div class="nb-toast nb-toast--{{ $tone }}" data-auto role="status">
                    <span>{{ session('message') }}</span>
                    <button type="button" aria-label="إغلاق">✕</button>
                </div>
            @endif
            @if ($errors->any())
                <div class="nb-toast nb-toast--error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" aria-label="إغلاق">✕</button>
                </div>
            @endif
        </div>

        @include('layouts.assets.script')
        @stack('scripts')
    </body>
</html>
