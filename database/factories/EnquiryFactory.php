<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnquiryFactory extends Factory
{
    protected $model = Enquiry::class;

    public function definition(): array
    {
        return [
            'customer_id' => fake()->optional(0.7)->passthrough(Customer::factory()),
            'source' => fake()->randomElement(['web', 'phone', 'email', 'referral', 'other']),
            'status' => fake()->randomElement(['new', 'in_progress', 'responded', 'closed']),
            'subject' => fake()->optional()->sentence(3),
            'message' => fake()->paragraph(),
            'responded_at' => null,
            'staff_user_id' => null,
        ];
    }
}
