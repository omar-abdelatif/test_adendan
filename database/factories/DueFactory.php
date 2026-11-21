<?php

namespace Database\Factories;

use App\Models\Due;
use App\Models\Subscribers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    protected $model = Due::class;

    public function definition(): array
    {
        return [
            'member_id' => fn () => Subscribers::factory()->create()->member_id,
            'item' => (string) now()->year,
            'total_amount' => 300,
            'amount_paid' => 0,
            'amount_remaining' => 300,
            'transaction_type' => 'إشتراك',
        ];
    }

    public function forMember(Subscribers $member): static
    {
        return $this->state(['member_id' => $member->member_id]);
    }

    public function amount(int $total, int $paid = 0): static
    {
        return $this->state(['total_amount' => $total, 'amount_paid' => $paid, 'amount_remaining' => $total - $paid]);
    }

    public function donation(string $item = 'تبرع سيارة التكريم'): static
    {
        return $this->state(['item' => $item, 'transaction_type' => 'تبرعات']);
    }
}
