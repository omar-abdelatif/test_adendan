@php
    $me = Auth::user();
    $avatar = $me->avatar ? asset('assets/images/users/'.$me->avatar) : null;
@endphp
@extends('layouts.master')
@section('title', 'حسابي')
@section('content')
    <x-nb.page title="حسابي" subtitle="بياناتك كموظف في الداشبورد" />

    <div class="nb-split">
        <form action="{{ route('user.update') }}" method="POST" enctype="multipart/form-data" class="nb-card">
            @csrf
            <input type="hidden" name="id" value="{{ $me->id }}">
            <header class="nb-card__head">
                <span class="nb-card__icon" aria-hidden="true"><i data-feather="user"></i></span>
                <h2 class="nb-card__title">تعديل البيانات</h2>
            </header>
            <div class="nb-card__body">
                <div class="nb-form-grid">
                    <x-nb.field label="الإسم بالكامل" name="name" :value="$me->name" required />
                    <x-nb.field label="البريد الإلكتروني" name="email" type="email" dir="ltr" :value="$me->email" readonly hint="البريد مينفعش يتغير من هنا" />
                    <x-nb.field label="كلمة سر جديدة" name="password" type="password" dir="ltr" autocomplete="new-password" hint="سيبها فاضية لو مش هتغيّرها" />
                    <x-nb.field label="الصورة" name="avatar" type="file" accept="image/*" preview="#me-photo" />
                </div>
            </div>
            <footer class="nb-card__foot">
                <x-nb.button type="submit" icon="check">حفظ التعديلات</x-nb.button>
            </footer>
        </form>

        <x-nb.card>
            <div class="nb-stack-sm" style="align-items: center; text-align: center">
                <x-nb.avatar :name="$me->name" :src="$avatar" size="xl" tone="teal" />
                <img id="me-photo" hidden alt="" class="nb-thumb" style="width: 132px; height: 150px; border-radius: 999px 999px 20px 20px">
                <h2 style="font-size: 20px">{{ $me->name }}</h2>
                <div class="nb-chips" style="justify-content: center">
                    @foreach ($me->roles as $role)
                        <x-nb.badge :tone="$role->name === 'master' ? 'ink' : 'teal'">{{ $role->name === 'master' ? 'مدير النظام' : $role->name }}</x-nb.badge>
                    @endforeach
                </div>
                <span class="nb-muted" dir="ltr">{{ $me->email }}</span>
            </div>
        </x-nb.card>
    </div>
@endsection
