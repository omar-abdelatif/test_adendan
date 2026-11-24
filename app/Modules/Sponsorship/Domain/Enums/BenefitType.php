<?php

namespace App\Modules\Sponsorship\Domain\Enums;

/** What the case gets: money, or goods (food, clothes). */
enum BenefitType: string
{
    case Cash = 'cash';
    case InKind = 'in_kind';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدية',
            self::InKind => 'عينية',
        };
    }

    /** The old system and the Excel sheets write the Arabic word. */
    public static function fromText(?string $text): ?self
    {
        $text = trim((string) $text);

        return self::tryFrom($text) ?? match (true) {
            str_contains($text, 'نقد') => self::Cash,
            str_contains($text, 'عين') => self::InKind,
            default => null,
        };
    }

    /** @return array<string, string> value => label, for the forms */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
