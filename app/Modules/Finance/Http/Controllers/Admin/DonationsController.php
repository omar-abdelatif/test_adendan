<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Actions\IssueDues;
use App\Modules\Finance\Application\Actions\RecordDonation;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Http\Requests\IssueDuesRequest;
use App\Modules\Finance\Http\Requests\MemberDonationRequest;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Carbon\CarbonImmutable;

class DonationsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:التبرعات');
    }

    /** A cash donation from a member, received at the office. */
    public function store(MemberDonationRequest $request, RecordDonation $recordDonation)
    {
        $member = Subscribers::where('member_id', $request->integer('member_id'))->firstOrFail();

        $recordDonation->fromMember(
            member: $member,
            amount: Money::pounds($request->input('amount')),
            donationType: $request->string('donation_type'),
            category: $request->input('donation_category'),
            otherDonation: $request->input('other_donation'),
            invoiceNo: $request->input('invoice_no'),
            date: $request->filled('payment_date') ? CarbonImmutable::parse($request->input('payment_date')) : CarbonImmutable::now(),
        );

        return $this->success('تم الإضافة بنجاح');
    }

    /** A donation campaign charged to every active member as a due. */
    public function issueDonationDues(IssueDuesRequest $request, IssueDues $issueDues)
    {
        return $this->attempt(
            fn () => $issueDues->handle(DueType::Donation, $request->item(), $request->amount()),
            'تم أضافة مديونية التبرعات للمشتركين بنجاح',
        );
    }
}
