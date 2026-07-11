<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\EmailSent;
use App\Models\EmailTemplate;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailSentFactory extends Factory
{
    protected $model = EmailSent::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'quote_id' => Quote::factory(),
            'template_id' => EmailTemplate::factory(),
            'to_email' => fake()->safeEmail(),
            'subject' => 'Your Quote from Quantock Van Tech',
            'body_html' => '<p>Test email body</p>',
            'postmark_message_id' => null,
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ];
    }
}
