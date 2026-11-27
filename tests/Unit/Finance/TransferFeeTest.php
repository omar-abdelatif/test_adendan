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
        // amount in pounds, fee in piasters (3 EGP + 2.75%, rounded up)
        return [
            '100 EGP' => [100, 575],
            '150 EGP: 4.125 rounds up' => [150, 713],
            '10 EGP' => [10, 328],
            '60 EGP' => [60, 465],
            'nothing to pay, no fee' => [0, 0],
        ];
    }

    #[DataProvider('amounts')]
    public function test_fee_is_three_pounds_plus_two_point_seventy_five_percent(int $pounds, int $fee): void
    {
        $this->assertSame($fee, (new TransferFee(300, 275))->for(Money::pounds($pounds))->piasters);
    }
}
