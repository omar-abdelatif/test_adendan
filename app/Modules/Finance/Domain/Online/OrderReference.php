<?php

namespace App\Modules\Finance\Domain\Online;

use InvalidArgumentException;

/**
 * What an online payment is for, carried through the provider as the merchant
 * order id and read back from the callback.
 *
 * Format (kept from the existing mobile app): memberId-item-purpose-ref-mobile.
 * `ref` makes every order unique, because the provider rejects a reused id.
 */
final class OrderReference
{
    public const SMS_ITEM = 'رسائل';

    public function __construct(
        public readonly int $memberId,
        public readonly string $item,
        public readonly string $purpose,
        public readonly string $ref,
        public readonly string $mobile,
    ) {
        foreach (['item' => $item, 'purpose' => $purpose, 'ref' => $ref, 'mobile' => $mobile] as $name => $part) {
            if (str_contains($part, '-')) {
                throw new InvalidArgumentException("The {$name} of an order reference cannot contain '-'.");
            }
        }
    }

    public static function parse(string $merchantOrderId): self
    {
        $parts = array_map('trim', explode('-', $merchantOrderId));
        if (count($parts) < 5 || ! ctype_digit($parts[0])) {
            throw new InvalidArgumentException('Malformed merchant order id.');
        }

        return new self((int) $parts[0], $parts[1], $parts[2], $parts[3], $parts[4]);
    }

    public function isSms(): bool
    {
        return $this->item === self::SMS_ITEM;
    }

    /** Ref for a due payment: "d<due id>x<random>", so the callback finds the exact due. */
    public static function dueRef(int $dueId, string $random): string
    {
        return 'd'.$dueId.'x'.$random;
    }

    /** The due id, for orders created by StartOnlineCheckout; null for older app orders. */
    public function dueId(): ?int
    {
        return preg_match('/^d(\d+)x/', $this->ref, $m) ? (int) $m[1] : null;
    }

    public function __toString(): string
    {
        return implode('-', [$this->memberId, $this->item, $this->purpose, $this->ref, $this->mobile]);
    }
}
