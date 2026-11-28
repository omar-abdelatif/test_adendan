@php
    $members = \App\Models\Subscribers::whereIn('member_id', $requests->pluck('member_id'))->get()->keyBy('member_id');
    $fields = ['name' => ['الاسم', 'name'], 'address' => ['العنوان', 'address'], 'mobile_no' => ['رقم الهاتف', 'mobile_no'], 'ssn' => ['الرقم القومي', 'ssn'], 'birth_date' => ['تاريخ الميلاد', 'birthdate'], 'job_title' => ['المهنة', 'job']];
@endphp
@extends('layouts.master')
@section('title', 'طلبات تحديث البيانات')
@section('content')
    <x-nb.page title="طلبات تحديث البيانات" subtitle="التعديلات اللي الأعضاء طلبوها من التطبيق، ومستنية موافقتك" :crumbs="['التقارير']" />

    @if ($requests->isEmpty())
        <x-nb.card><x-nb.empty icon="check-circle" title="مفيش طلبات مستنية" text="أول ما عضو يطلب تعديل من التطبيق هيظهر هنا." /></x-nb.card>
    @else
        <div class="nb-cols" style="--cols: 2">
            @foreach ($requests as $request)
                @php($member = $members[$request->member_id] ?? null)
                <x-nb.card :title="$member?->name ?? $request->name" :subtitle="'عضوية '.$request->member_id.' · طلب '.$request->created_at?->locale('ar')->diffForHumans()" icon="refresh-cw" tone="gold">
                    <div class="nb-stack-sm">
                        @foreach ($fields as $key => [$label, $column])
                            @continue(blank($request->{$key}) || ($member && (string) $member->{$column} === (string) $request->{$key}))
                            @php($before = $member?->{$column})
                            <div class="nb-row" style="flex-wrap: nowrap; align-items: baseline">
                                <span class="nb-label" style="width: 110px; flex: none">{{ $label }}</span>
                                <span class="nb-muted" style="text-decoration: line-through">{{ filled($before) ? $before : '—' }}</span>
                                <i data-feather="arrow-left" class="nb-muted"></i>
                                <span class="nb-in">{{ $request->{$key} }}</span>
                            </div>
                        @endforeach
                    </div>
                    <x-slot:footer>
                        <form action="{{ route('user.declineUpdateRequest', $request->id) }}" method="POST" data-nb-ajax data-nb-remove=".nb-card">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="member_id" value="{{ $request->member_id }}">
                            <x-nb.button type="submit" variant="ghost" icon="x" class="nb-out">رفض</x-nb.button>
                        </form>
                        <form action="{{ route('user.updateRequest') }}" method="POST" data-nb-ajax data-nb-remove=".nb-card">
                            @csrf
                            <input type="hidden" name="name" value="{{ $request->name }}">
                            <input type="hidden" name="member_id" value="{{ $request->member_id }}">
                            <input type="hidden" name="mobile_no" value="{{ $request->mobile_no }}">
                            <input type="hidden" name="ssn" value="{{ $request->ssn }}">
                            <input type="hidden" name="address" value="{{ $request->address }}">
                            <input type="hidden" name="job_title" value="{{ $request->job_title }}">
                            <input type="hidden" name="birthdate" value="{{ $request->birth_date }}">
                            <x-nb.button type="submit" icon="check">موافقة وتحديث الملف</x-nb.button>
                        </form>
                    </x-slot:footer>
                </x-nb.card>
            @endforeach
        </div>
    @endif
@endsection
