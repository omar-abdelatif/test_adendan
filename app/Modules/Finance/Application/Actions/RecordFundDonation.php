<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Donators;
use App\Models\FundDonation;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\PaymentMethod;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Donations to the sponsorship and new-tombs funds.
 *
 * Every donation writes one ledger entry into its fund's safe or bank. A balance
 * is never edited by hand: changing the amount or the account writes an entry
 * that takes the old amount back out and a new entry for the new one, and
 * removing a donation writes the entry that takes it out. If that money already
 * left the account (moved to the bank, say), the change is refused.
 *
 * @phpstan-type DonationData array{amount: int, account?: string, invoice_no?: ?string, category?: ?string, category_other?: ?string, months?: ?list<string>, donated_on: string, notes?: ?string}
 */
class RecordFundDonation
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @param  DonationData  $data */
    public function record(Donators $donor, Fund $fund, array $data): FundDonation
    {
        if (! in_array($fund, Fund::separate(), true)) {
            throw new LogicException('The association fund records donations through RecordDonation.');
        }

        return DB::transaction(function () use ($donor, $fund, $data) {
            $donation = new FundDonation(['fund' => $fund, 'donator_id' => $donor->id] + $this->clean($fund, $data));
            $donation->save();

            $entry = $this->ledger->record($this->entry($donation, Direction::In, TransactionType::Donation, "تبرع {$fund->label()}: {$donor->name}", $donation->donated_on->toImmutable()));
            $donation->forceFill(['transaction_id' => $entry->id])->saveQuietly();

            return $donation;
        });
    }

    /** @param  DonationData  $data */
    public function update(FundDonation $donation, array $data): FundDonation
    {
        return DB::transaction(function () use ($donation, $data) {
            $donation = FundDonation::query()->with('donor')->lockForUpdate()->findOrFail($donation->id);
            $before = clone $donation;
            $donation->fill($this->clean($donation->fund, $data));

            if ($donation->amount !== $before->amount || $donation->account !== $before->account) {
                $this->ledger->record($this->entry($before, Direction::Out, TransactionType::DonationCorrection, $this->correctionItem('تصحيح', $before), CarbonImmutable::now()));
                $entry = $this->ledger->record($this->entry($donation, Direction::In, TransactionType::Donation, "تبرع {$donation->fund->label()} بعد التصحيح: {$donation->donor?->name}", CarbonImmutable::now()));
                $donation->transaction_id = $entry->id;
            }
            $donation->save();

            return $donation;
        });
    }

    public function delete(FundDonation $donation): void
    {
        DB::transaction(function () use ($donation) {
            $donation = FundDonation::query()->with('donor')->lockForUpdate()->findOrFail($donation->id);
            $this->ledger->record($this->entry($donation, Direction::Out, TransactionType::DonationCorrection, $this->correctionItem('إلغاء', $donation), CarbonImmutable::now()));
            $donation->delete();
        });
    }

    private function entry(FundDonation $donation, Direction $direction, TransactionType $type, string $item, CarbonImmutable $date): LedgerEntry
    {
        return new LedgerEntry(
            type: $type,
            direction: $direction,
            amount: Money::pounds($donation->amount),
            item: $item,
            account: $donation->account,
            paymentMethod: $donation->account === Account::Bank ? 'تحويل بنكي' : PaymentMethod::Cash->value,
            invoiceNo: $donation->invoice_no,
            date: $date,
            fund: $donation->fund,
        );
    }

    private function correctionItem(string $what, FundDonation $donation): string
    {
        $receipt = $donation->invoice_no ? " إيصال {$donation->invoice_no}" : '';

        return "{$what} تبرع {$donation->fund->label()}{$receipt}: {$donation->donor?->name}";
    }

    /**
     * @param  DonationData  $data
     * @return array<string, mixed>
     */
    private function clean(Fund $fund, array $data): array
    {
        $categories = $fund->donationCategories();
        $category = $categories ? ($data['category'] ?? null) : null;
        $months = array_values(array_intersect(FundDonation::MONTHS, $data['months'] ?? []));

        return [
            'amount' => (int) $data['amount'],
            'account' => Account::tryFrom($data['account'] ?? '') ?? Account::Safe,
            'invoice_no' => ($data['invoice_no'] ?? null) ?: null,
            'category' => in_array($category, $categories, true) ? $category : null,
            'category_other' => $category === 'أخرى' ? (($data['category_other'] ?? null) ?: null) : null,
            'months' => $months ?: null,
            'donated_on' => $data['donated_on'],
            'notes' => ($data['notes'] ?? null) ?: null,
        ];
    }
}
