<?php

namespace App\Modules\Finance\Domain\Enums;

/** What a member owes (column `dues.transaction_type`). */
enum DueType: string
{
    case Subscription = 'إشتراك';
    case Donation = 'تبرعات';

    /** The ledger type a payment against this due is recorded as. */
    public function transactionType(): TransactionType
    {
        return match ($this) {
            self::Subscription => TransactionType::Subscription,
            self::Donation => TransactionType::Donation,
        };
    }
}
