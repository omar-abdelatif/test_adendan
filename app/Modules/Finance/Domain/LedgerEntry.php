<?php

namespace App\Modules\Finance\Domain;

use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Channel;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\PaymentCategory;
use App\Modules\Finance\Domain\Enums\PaymentMethod;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonInterface;

/**
 * One movement of money, as it will be written to payment_transactions.
 *
 * $account is where the money physically moves (safe or bank). Null means it
 * moves outside our accounts (an online payment held by Paymob), so no balance
 * changes. $fund is whose money it is; each fund has its own safe and bank balance.
 */
final class LedgerEntry
{
    public function __construct(
        public readonly TransactionType $type,
        public readonly Direction $direction,
        public readonly Money $amount,
        public readonly string $item,
        public readonly ?Account $account = Account::Safe,
        public readonly ?int $memberId = null,
        public readonly PaymentCategory $category = PaymentCategory::Full,
        public readonly string $paymentMethod = PaymentMethod::Cash->value,
        public readonly Channel $channel = Channel::Office,
        public readonly int|string|null $invoiceNo = null,
        public readonly ?CarbonInterface $date = null,
        public readonly ?string $proofImage = null,
        public readonly ?string $paymobOrderId = null,
        public readonly ?string $paymobTransactionId = null,
        public readonly ?string $paymobStatus = null,
        public readonly Fund $fund = Fund::General,
    ) {}
}
