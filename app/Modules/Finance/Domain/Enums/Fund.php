<?php

namespace App\Modules\Finance\Domain\Enums;

/**
 * Whose money it is. Each fund keeps its own safe and bank balance, so money
 * given for sponsorship or for the new tombs never mixes with the association's.
 */
enum Fund: string
{
    case General = 'general';
    case Sponsorship = 'sponsorship';
    case NewTombs = 'new_tombs';

    public function label(): string
    {
        return match ($this) {
            self::General => 'الجمعية',
            self::Sponsorship => 'الكفالة',
            self::NewTombs => 'المقابر الجديدة',
        };
    }

    /**
     * Permission for the fund's donors and donations. The association's fund
     * keeps its own screens and permissions, so it has none here.
     */
    public function permission(): ?string
    {
        return match ($this) {
            self::General => null,
            self::Sponsorship => 'الكفالة',
            self::NewTombs => 'المقابر',
        };
    }

    /** Permission for the fund's safe and bank page and its transfers. */
    public function accountsPermission(): ?string
    {
        return match ($this) {
            self::General => null,
            self::Sponsorship => 'خزنة الكفالة',
            self::NewTombs => 'خزنة المقابر الجديدة',
        };
    }

    /**
     * What a donation to this fund can be for ("أخرى" asks for a description).
     * New-tomb donations all go to building the tombs, so they have no choice.
     *
     * @return list<string>
     */
    public function donationCategories(): array
    {
        return match ($this) {
            self::Sponsorship => ['صدقات', 'زكاة مال', 'زكاة فطر', 'كفالة يتيم', 'أخرى'],
            default => [],
        };
    }

    /** @return list<self> the funds the dashboard's fund pages serve */
    public static function separate(): array
    {
        return [self::Sponsorship, self::NewTombs];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $fund) => $fund->value, self::cases());
    }
}
