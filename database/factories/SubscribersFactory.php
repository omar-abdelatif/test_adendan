<?php

namespace Database\Factories;

use App\Models\Subscribers;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscribers>
 */
class SubscribersFactory extends Factory
{
    protected $model = Subscribers::class;

    public function definition(): array
    {
        $name = 'عضو '.fake()->unique()->numberBetween(1, 999999);

        return [
            'member_id' => fake()->unique()->numberBetween(1000, 999999),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'ssn' => fake()->unique()->numberBetween(29000000000000, 29999999999999),
            'birthdate' => fake()->date('Y-m-d', '-20 years'),
            'mobile_no' => fake()->unique()->numberBetween(1000000000, 1599999999),
            'address' => 'أدندان',
            'status' => 1,
            'membership_type' => 'عضوية',
        ];
    }

    public function resigned(): static
    {
        return $this->state(['status' => 2]);
    }
}
