<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_is_built_from_pounds_and_cents(): void
    {
        $this->assertSame(15050, Money::pounds('150.5')->piasters);
        $this->assertSame(10000, Money::cents(10000)->piasters);
        $this->assertTrue(Money::pounds(null)->isZero());
        $this->assertTrue(Money::pounds('')->isZero());
    }

    public function test_it_rounds_half_up_to_whole_pounds_for_storage(): void
    {
        $this->assertSame(150, Money::pounds(150.49)->toPounds());
        $this->assertSame(151, Money::pounds(150.5)->toPounds());
        $this->assertTrue(Money::pounds(150.5)->hasFraction());
        $this->assertFalse(Money::pounds(150)->hasFraction());
    }

    public function test_arithmetic_and_comparison(): void
    {
        $a = Money::pounds(100);
        $b = Money::pounds(30);

        $this->assertSame(130, $a->plus($b)->toPounds());
        $this->assertSame(70, $a->minusFloor($b)->toPounds());
        $this->assertTrue($b->minusFloor($a)->isZero());
        $this->assertSame(30, $a->min($b)->toPounds());
        $this->assertTrue($a->greaterThan($b));
        $this->assertTrue($a->greaterThanOrEqual(Money::pounds(100)));
        $this->assertTrue($a->equals(Money::pounds('100.00')));
    }

    public function test_it_prints_without_needless_decimals(): void
    {
        $this->assertSame('100', (string) Money::pounds(100));
        $this->assertSame('100.50', (string) Money::pounds(100.5));
        $this->assertSame(100, Money::pounds(100)->jsonSerialize());
    }

    public function test_it_rejects_negative_and_non_numeric_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::pounds(-1);
    }

    public function test_it_rejects_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::pounds('abc');
    }
}
