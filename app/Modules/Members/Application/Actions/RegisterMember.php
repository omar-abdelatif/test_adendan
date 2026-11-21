<?php

namespace App\Modules\Members\Application\Actions;

use App\Models\CostYears;
use App\Models\Due;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\FeeSchedule;
use App\Modules\Finance\Domain\FiscalCalendar;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Members\Domain\MemberException;
use App\Modules\Members\Domain\MemberStatus;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * A new member joins at the office. They pay, in cash, the rest of this
 * subscription year plus the joining fee, and the annual donation; both go
 * into the safe. They also start with the standard donation dues.
 */
class RegisterMember
{
    public const PHOTO_FOLDER = 'subscribers/avatar';

    public const ID_FOLDER = 'subscribers/id';

    public function __construct(
        private readonly Ledger $ledger,
        private readonly FeeSchedule $fees,
        private readonly FiscalCalendar $calendar,
        private readonly ImageStorage $images,
    ) {}

    /** @param array<string, mixed> $attributes validated member fields */
    public function handle(array $attributes, ?UploadedFile $photo, ?UploadedFile $idCard, int|string|null $invoiceNo): Subscribers
    {
        $today = now();
        $year = $this->calendar->subscriptionYear($today);
        $yearlyCost = CostYears::where('year', $year)->value('cost');
        if ($yearlyCost === null) {
            throw MemberException::yearCostMissing($year);
        }
        $firstSubscription = $this->fees->firstSubscription(Money::pounds($yearlyCost), $this->calendar->remainingMonths($today));

        $photoName = $photo ? $this->images->store($photo, self::PHOTO_FOLDER) : null;
        $idName = $idCard ? $this->images->store($idCard, self::ID_FOLDER) : null;

        try {
            return DB::transaction(function () use ($attributes, $photoName, $idName, $year, $firstSubscription, $invoiceNo) {
                $member = Subscribers::create([
                    ...$attributes,
                    'slug' => Str::slug((string) $attributes['name']).'-'.$attributes['member_id'],
                    'img' => $photoName,
                    'id_img' => $idName,
                    'status' => MemberStatus::Active->value,
                ]);

                foreach ([
                    [TransactionType::NewSubscription, $firstSubscription],
                    [TransactionType::AnnualDonation, $this->fees->annualDonation()],
                ] as [$type, $amount]) {
                    $this->ledger->record(new LedgerEntry(
                        type: $type,
                        direction: Direction::In,
                        amount: $amount,
                        item: (string) $year,
                        memberId: (int) $member->member_id,
                        invoiceNo: $invoiceNo,
                    ));
                }

                foreach ($this->fees->newMemberDues() as $due) {
                    Due::create([
                        'member_id' => $member->member_id,
                        'item' => $due['item'],
                        'total_amount' => $due['amount']->toPounds(),
                        'amount_paid' => 0,
                        'amount_remaining' => $due['amount']->toPounds(),
                        'transaction_type' => DueType::Donation->value,
                    ]);
                }

                return $member;
            });
        } catch (Throwable $e) {
            $this->images->delete($photoName, self::PHOTO_FOLDER);
            $this->images->delete($idName, self::ID_FOLDER);
            throw $e;
        }
    }
}
