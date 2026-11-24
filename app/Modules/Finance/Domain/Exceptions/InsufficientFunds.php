<?php

namespace App\Modules\Finance\Domain\Exceptions;

use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Shared\Domain\Money;

final class InsufficientFunds extends FinanceException
{
    public function __construct(
        public readonly Account $account,
        public readonly Money $balance,
        public readonly Money $requested,
        public readonly Fund $fund = Fund::General,
    ) {
        $where = $fund === Fund::General ? $account->label() : "{$account->label()} {$fund->label()}";
        parent::__construct("المبلغ المطلوب ({$requested}) أكبر من رصيد {$where} ({$balance}).");
    }
}
