<?php

namespace App\Modules\Shared\Domain;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of Egyptian pounds, held in piasters so arithmetic never drifts.
 *
 * The database stores whole pounds (integer columns); use pounds() at that
 * boundary. Amounts are never negative: a withdrawal is a positive amount
 * moving out, not a negative one.
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(public readonly int $piasters)
    {
        if ($piasters < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function pounds(int|float|string|null $amount): self
    {
        if ($amount === null || $amount === '') {
            return self::zero();
        }
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Not an amount: [{$amount}].");
        }

        return new self((int) round(((float) $amount) * 100));
    }

    /** Paymob reports amounts in cents (piasters). */
    public static function cents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Whole pounds for the integer amount columns, rounding half up. */
    public function toPounds(): int
    {
        return intdiv($this->piasters + 50, 100);
    }

    public function hasFraction(): bool
    {
        return $this->piasters % 100 !== 0;
    }

    public function plus(self $other): self
    {
        return new self($this->piasters + $other->piasters);
    }

    /** Subtracts, never going below zero. */
    public function minusFloor(self $other): self
    {
        return new self(max(0, $this->piasters - $other->piasters));
    }

    public function min(self $other): self
    {
        return $this->piasters <= $other->piasters ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->piasters === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->piasters > $other->piasters;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->piasters >= $other->piasters;
    }

    public function equals(self $other): bool
    {
        return $this->piasters === $other->piasters;
    }

    public function __toString(): string
    {
        return $this->hasFraction()
            ? number_format($this->piasters / 100, 2, '.', '')
            : (string) intdiv($this->piasters, 100);
    }

    public function jsonSerialize(): int|float
    {
        return $this->hasFraction() ? $this->piasters / 100 : intdiv($this->piasters, 100);
    }
}
