<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnquiryReplyFactory extends Factory
{
    protected $model = EnquiryReply::class;

    public function definition(): array
    {
        return [
            'enquiry_id' => Enquiry::factory(),
            'staff_user_id' => User::factory(),
            'direction' => 'outbound',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'to_email' => fake()->email(),
            'status' => 'sent',
            'sent_at' => now(),
        ];
    }
}
