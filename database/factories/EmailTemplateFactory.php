<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'subject' => 'Your Quote from Quantock Van Tech — {{ quote_reference }}',
            'body_html' => '<p>Dear {{ customer_name }},</p><p>Please find your quote <strong>{{ quote_reference }}</strong> attached.</p><p>Valid until: {{ valid_until }}</p><p>Total: {{ grand_total }}</p>',
            'body_text' => 'Dear {{ customer_name }},\n\nPlease find your quote {{ quote_reference }} attached.\n\nValid until: {{ valid_until }}\n\nTotal: {{ grand_total }}',
            'variables_json' => ['quote_reference', 'customer_name', 'valid_until', 'grand_total', 'custom_message'],
            'is_active' => true,
        ];
    }
}
