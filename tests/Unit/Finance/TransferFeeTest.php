<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\TransferFee;
use App\Modules\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransferFeeTest extends TestCase
{
    public static function amounts(): array
    {
        // amount in pounds, fee in piasters: (amount + 3 EGP) / (1 - 2.75%), rounded up
        return [
            '100 EGP' => [100, 592],
            '150 EGP' => [150, 733],
            '10 EGP' => [10, 337],
            '60 EGP' => [60, 479],
            'nothing to pay, no fee' => [0, 0],
        ];
    }

    #[DataProvider('amounts')]
    public function test_the_association_keeps_the_full_amount_after_paymobs_cut(int $pounds, int $fee): void
    {
        $this->assertSame($fee, (new TransferFee(300, 275))->for(Money::pounds($pounds))->piasters);
    }
}
