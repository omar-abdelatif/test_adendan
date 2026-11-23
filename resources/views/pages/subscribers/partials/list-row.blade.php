{{-- One member in the members list (MembersController@table). Blade escapes every value. --}}
@php($state = \App\Modules\Members\Domain\MemberStatus::tryFrom((int) $row->status))
<button type="button" class="nb-mrow" data-id="{{ $row->id }}" data-preview="{{ route('subscriber.preview', $row->id) }}" data-open="{{ route('subscriber.details', $row->id) }}">
    <x-nb.avatar :name="$row->name" :src="$row->img ? asset('assets/images/subscribers/avatar/'.$row->img) : null" size="sm" />
    <span class="nb-mrow__main">
        <strong>{{ $row->name }}</strong>
        <span><bdi>{{ $row->member_id }}</bdi> · {{ $row->address ?: 'العنوان ناقص' }}</span>
    </span>
    <i class="nb-mrow__dot nb-mrow__dot--{{ $state?->value ?? 'none' }}" title="{{ $state?->label() }}" aria-hidden="true"></i>
    <span class="nb-sr">{{ $state?->label() }}</span>
</button>
