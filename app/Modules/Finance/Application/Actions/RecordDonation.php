<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Donations;
use App\Models\Donators;
use App\Models\OuterDonations;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cash donations received at the office, from members or outside donors.
 * Both go into the safe.
 */
class RecordDonation
{
    public function __construct(private readonly Ledger $ledger) {}

    public function fromMember(
        Subscribers $member,
        Money $amount,
        string $donationType,
        ?string $category,
        ?string $otherDonation,
        int|string|null $invoiceNo,
        CarbonInterface $date,
    ): Donations {
        return DB::transaction(function () use ($member, $amount, $donationType, $category, $otherDonation, $invoiceNo, $date) {
            $donation = Donations::create([
                'member_id' => $member->member_id,
                'subscribers_id' => $member->id,
                'invoice_no' => $invoiceNo,
                'amount' => $amount->toPounds(),
                'donation_type' => $donationType,
                'donation_category' => $category,
                'other_donation' => $otherDonation,
                'payment_date' => $date->toDateString(),
            ]);

            $this->ledger->record(new LedgerEntry(
                type: TransactionType::Donation,
                direction: Direction::In,
                amount: $amount,
                item: $category ?: ($otherDonation ?: $donationType),
                memberId: (int) $member->member_id,
                invoiceNo: $invoiceNo,
                date: $date,
            ));

            return $donation;
        });
    }

    /**
     * @param  array{invoice_id: int|string, donation_destination: string, payment_method: string, donator_type?: ?string}  $details
     */
    public function fromDonor(Donators $donor, Money $amount, array $details, CarbonInterface $date): OuterDonations
    {
        return DB::transaction(function () use ($donor, $amount, $details, $date) {
            $donation = OuterDonations::create([
                'donators_id' => $donor->id,
                'name' => $donor->name,
                'donator_type' => $details['donator_type'] ?? $donor->donator_type,
                'invoice_id' => $details['invoice_id'],
                'amount' => $amount->toPounds(),
                'donation_destination' => $details['donation_destination'],
                'payment_method' => $details['payment_method'],
            ]);

            $this->ledger->record(new LedgerEntry(
                type: TransactionType::OuterDonation,
                direction: Direction::In,
                amount: $amount,
                item: 'تبرع خارجي: '.$donor->name,
                paymentMethod: $details['payment_method'],
                invoiceNo: $details['invoice_id'],
                date: $date,
            ));

            return $donation;
        });
    }
}
