<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Channel;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Moves cash between the safe and the bank: one "out" entry on the source and
 * one "in" entry on the destination, both pointing at the same receipt image.
 */
class TransferFunds
{
    public const PROOF_FOLDER = 'withdraws';

    public function __construct(private readonly Ledger $ledger, private readonly ImageStorage $images) {}

    public function handle(Account $from, Account $to, Money $amount, UploadedFile $proof, CarbonInterface $date): void
    {
        if ($from === $to) {
            throw FinanceException::sameAccount();
        }

        $proofName = $this->images->store($proof, self::PROOF_FOLDER);
        $item = "تحويل من {$from->label()} إلى {$to->label()}";

        try {
            DB::transaction(function () use ($from, $to, $amount, $proofName, $item, $date) {
                foreach ([[$from, Direction::Out], [$to, Direction::In]] as [$account, $direction]) {
                    $this->ledger->record(new LedgerEntry(
                        type: TransactionType::Transfer,
                        direction: $direction,
                        amount: $amount,
                        item: $item,
                        account: $account,
                        channel: Channel::Transfer,
                        date: $date,
                        proofImage: self::PROOF_FOLDER.'/'.$proofName,
                    ));
                }
            });
        } catch (Throwable $e) {
            $this->images->delete($proofName, self::PROOF_FOLDER);
            throw $e;
        }
    }
}
