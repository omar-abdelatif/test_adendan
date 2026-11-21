<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition(): array
    {
        return [
            'title' => 'خبر '.fake()->unique()->numberBetween(1, 99999),
            'description' => '<p>نص الخبر</p>',
            'category' => 'أخبار عامة',
            'slug' => fake()->unique()->uuid(),
            'posted_by' => 'admin',
        ];
    }
}
