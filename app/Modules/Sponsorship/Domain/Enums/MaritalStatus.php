<?php

namespace App\Modules\Sponsorship\Domain\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Divorced = 'divorced';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'أعزب/عزباء',
            self::Married => 'متزوج/ة',
            self::Widowed => 'أرمل/ة',
            self::Divorced => 'مطلق/ة',
        };
    }

    /**
     * The old add form saved English words (single, widow…) and its edit form
     * saved Arabic ones (أرمل/ة…), so both spellings are read.
     */
    public static function fromText(?string $text): ?self
    {
        $text = mb_strtolower(trim((string) $text));

        return self::tryFrom($text) ?? match (true) {
            $text === '' => null,
            in_array($text, ['widow', 'widower'], true) || str_contains($text, 'أرمل') || str_contains($text, 'ارمل') => self::Widowed,
            str_contains($text, 'مطلق') => self::Divorced,
            str_contains($text, 'متزوج') => self::Married,
            str_contains($text, 'أعزب') || str_contains($text, 'اعزب') || str_contains($text, 'عزباء') => self::Single,
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
