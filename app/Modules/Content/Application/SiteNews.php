<?php

namespace App\Modules\Content\Application;

use App\Models\News;
use App\Modules\Content\Domain\NewsCategory;

/** News as the public website shows it. */
class SiteNews
{
    /** Home page: the 10 latest, plus 8 per category with each category's total. */
    public function homePage(): array
    {
        $totals = News::query()->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');
        $data = ['latest' => News::latest()->take(10)->get()];

        foreach (NewsCategory::cases() as $category) {
            $data[$category->viewKey()] = News::where('category', $category->value)->latest()->take(8)->get();
            $data[$category->viewKey().'count'] = (int) ($totals[$category->value] ?? 0);
        }

        return $data;
    }

    /** News page: 12 per category, each category with its own page number in the URL. */
    public function byCategory(): array
    {
        $data = [];

        foreach (NewsCategory::cases() as $category) {
            $page = News::where('category', $category->value)->latest()->paginate(12, ['*'], $category->viewKey().'_page');
            $data[$category->viewKey()] = $page;
            $data[$category->viewKey().'count'] = $page->total();
        }

        return $data;
    }
}
