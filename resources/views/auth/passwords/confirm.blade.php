@extends('layouts.guest')
@section('title', 'تأكيد كلمة السر')
@section('content')
    <div>
        <h1>تأكيد كلمة السر</h1>
        <p>اكتب كلمة السر تاني قبل ما تكمّل.</p>
    </div>
    <form method="POST" action="{{ route('password.confirm') }}" class="nb-form" novalidate>
        @csrf
        <x-nb.field label="كلمة السر" name="password" type="password" dir="ltr" required autocomplete="current-password" id="password" />
        <button type="submit" class="nb-btn nb-btn--block">تأكيد</button>
    </form>
    @if (Route::has('password.request'))
        <div class="nb-login__links"><a href="{{ route('password.request') }}">نسيت كلمة السر؟</a></div>
    @endif
@endsection
