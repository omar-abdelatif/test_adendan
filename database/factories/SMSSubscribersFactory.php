<?php

namespace Database\Factories;

use App\Models\SMSSubscribers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SMSSubscribers>
 */
class SMSSubscribersFactory extends Factory
{
    protected $model = SMSSubscribers::class;

    public function definition(): array
    {
        return [
            // A non-member by default; set member_id to an existing member when needed.
            'member_id' => null,
            'mobile_no' => fake()->unique()->numberBetween(1000000000, 1599999999),
            'amount' => 60,
            'subscription_start_date' => now()->toDateString(),
            'subscription_expiry_date' => now()->addYear()->toDateString(),
            'active_sms' => 1,
        ];
    }
}
