<?php

namespace App\Modules\Sponsorship\Domain\Enums;

/** How often the case gets it: every month, or in the seasons (Ramadan, the feasts, school). */
enum BenefitPeriod: string
{
    case Monthly = 'monthly';
    case Seasonal = 'seasonal';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'شهرية',
            self::Seasonal => 'موسمية',
        };
    }

    public static function fromText(?string $text): ?self
    {
        $text = trim((string) $text);

        return self::tryFrom($text) ?? match (true) {
            str_contains($text, 'شهر') => self::Monthly,
            str_contains($text, 'موسم') => self::Seasonal,
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
