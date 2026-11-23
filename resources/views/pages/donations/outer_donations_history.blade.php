@extends('layouts.master')
@section('title', 'تبرعات '.$donators->name)
@section('content')
    <x-nb.page :title="'تبرعات '.$donators->name" :subtitle="$donators->donator_type.($donators->duration ? ' · '.$donators->duration : '').' · مسجل من '.$donators->created_at?->locale('ar')->translatedFormat('F Y')" :crumbs="['المتبرعين' => route('donators.all')]">
        <x-slot:actions>
            <x-nb.button variant="ghost" icon="arrow-right" :href="route('donators.all')">كل المتبرعين</x-nb.button>
        </x-slot:actions>
    </x-nb.page>

    <div class="nb-stack">
        <x-nb.stats>
            <x-nb.stat label="إجمالي التبرعات" :value="number_format($outerdonations->sum('amount'))" unit="ج.م" icon="gift" main />
            <x-nb.stat label="عدد التبرعات" :value="$outerdonations->count()" icon="layers" tone="gold" />
            <x-nb.stat label="آخر تبرع" :value="$outerdonations->max('created_at')?->format('Y-m-d') ?? '—'" icon="clock" tone="green" />
        </x-nb.stats>

        <x-nb.card title="التبرعات السابقة" icon="clock" flush>
            @if ($outerdonations->isEmpty())
                <x-nb.empty icon="gift" title="لسه مفيش تبرعات من المتبرع ده" />
            @else
                <x-nb.table id="donor-history" :order="[[0, 'desc']]">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>رقم الإيصال</th>
                            <th>جهة التبرع</th>
                            <th>طريقة الدفع</th>
                            <th class="is-end">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($outerdonations as $donation)
                            <tr>
                                <td class="nb-num">{{ $donation->created_at?->format('Y-m-d') }}</td>
                                <td class="nb-num">{{ $donation->invoice_id }}</td>
                                <td class="nb-strong">{{ $donation->donation_destination }}</td>
                                <td><x-nb.badge tone="teal">{{ $donation->payment_method }}</x-nb.badge></td>
                                <td class="is-end nb-in">{{ number_format($donation->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4">الإجمالي</td><td class="is-end">{{ number_format($outerdonations->sum('amount')) }} ج.م</td></tr>
                    </tfoot>
                </x-nb.table>
            @endif
        </x-nb.card>
    </div>
@endsection
