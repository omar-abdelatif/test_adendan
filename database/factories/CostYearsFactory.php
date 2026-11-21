<?php

namespace Database\Factories;

use App\Models\CostYears;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostYears>
 */
class CostYearsFactory extends Factory
{
    protected $model = CostYears::class;

    public function definition(): array
    {
        return [
            'year' => now()->year,
            'cost' => 360,
        ];
    }
}
