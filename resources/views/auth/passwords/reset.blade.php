@extends('layouts.guest')
@section('title', 'كلمة سر جديدة')
@section('content')
    <div>
        <h1>كلمة سر جديدة</h1>
        <p>اختار كلمة سر جديدة لحسابك.</p>
    </div>
    <form method="POST" action="{{ route('password.update') }}" class="nb-form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-nb.field label="البريد الإلكتروني" name="email" type="email" :value="$email ?? old('email')" dir="ltr" required autocomplete="email" id="email" />
        <x-nb.field label="كلمة السر الجديدة" name="password" type="password" dir="ltr" required autocomplete="new-password" id="password" />
        <x-nb.field label="تأكيد كلمة السر" name="password_confirmation" type="password" dir="ltr" required autocomplete="new-password" id="password-confirm" />
        <button type="submit" class="nb-btn nb-btn--block">حفظ كلمة السر</button>
    </form>
@endsection
