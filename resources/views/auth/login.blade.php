@extends('layouts.guest')
@section('title', 'تسجيل الدخول')
@section('content')
    <div>
        <h1>تسجيل الدخول</h1>
        <p>ادخل ببريدك الإلكتروني وكلمة السر بتوعك.</p>
    </div>
    <form method="POST" action="{{ route('login') }}" class="nb-form" novalidate>
        @csrf
        <x-nb.field label="البريد الإلكتروني" name="email" type="email" dir="ltr" required autocomplete="email" autofocus id="email" />
        <x-nb.field label="كلمة السر" name="password" type="password" dir="ltr" required autocomplete="current-password" id="password" />
        <div class="nb-login__links">
            <label class="nb-check" for="remember">
                <input id="remember" type="checkbox" name="remember" @checked(old('remember'))>
                افتكرني على الجهاز ده
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">نسيت كلمة السر؟</a>
            @endif
        </div>
        <button type="submit" class="nb-btn nb-btn--block">دخول</button>
    </form>
@endsection
