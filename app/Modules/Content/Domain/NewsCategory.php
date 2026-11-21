<?php

namespace App\Modules\Content\Domain;

/** news.category values, in the order the website shows them. */
enum NewsCategory: string
{
    case General = 'أخبار عامة';
    case Culture = 'أخبار ثقافية';
    case Condolences = 'عزاء';
    case Weddings = 'أفراح';
    case Sports = 'أخبار رياضية';

    /** The variable prefix the website views use for each category. */
    public function viewKey(): string
    {
        return match ($this) {
            self::General => 'generalnews',
            self::Culture => 'culturenews',
            self::Condolences => 'deathnews',
            self::Weddings => 'weddingnews',
            self::Sports => 'sportnews',
        };
    }
}
