@extends('layouts.guest')
@section('title', 'نسيت كلمة السر')
@section('content')
    <div>
        <h1>نسيت كلمة السر؟</h1>
        <p>اكتب البريد الإلكتروني بتاعك وهنبعتلك رابط تغيّر بيه كلمة السر.</p>
    </div>
    @if (session('status'))
        <div class="nb-alert" role="status"><i data-feather="check-circle"></i><span>{{ session('status') }}</span></div>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="nb-form" novalidate>
        @csrf
        <x-nb.field label="البريد الإلكتروني" name="email" type="email" dir="ltr" required autocomplete="email" autofocus id="email" />
        <button type="submit" class="nb-btn nb-btn--block">ابعت رابط تغيير كلمة السر</button>
    </form>
    <div class="nb-login__links"><a href="{{ route('login') }}">رجوع لتسجيل الدخول</a></div>
@endsection
