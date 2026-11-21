<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'member_id' => fake()->numberBetween(1000, 999999),
            'item' => (string) now()->year,
            'amount' => 100,
            'inv_no' => fake()->unique()->numberBetween(1, 9999999),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'كاش',
            'payment_cat' => 'كلي',
            'transaction_type' => 'إشتراك',
            'transaction_cat' => 'ايداع',
            'transaction_method' => 'المقر',
        ];
    }
}
