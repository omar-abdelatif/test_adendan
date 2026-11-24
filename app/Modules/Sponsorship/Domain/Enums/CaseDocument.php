<?php

namespace App\Modules\Sponsorship\Domain\Enums;

/** The papers in a case file. The ID card and the social research are required. */
enum CaseDocument: string
{
    case IdCard = 'id_card';
    case Research = 'research';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IdCard => 'صورة البطاقة',
            self::Research => 'بحث الحالة',
            self::Photo => 'صورة',
            self::Other => 'مستند تاني',
        };
    }

    /** @return list<self> */
    public static function required(): array
    {
        return [self::IdCard, self::Research];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
