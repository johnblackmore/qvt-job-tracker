<?php

use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            ['name' => 'Stock', 'slug' => 'stock', 'description' => 'Products and materials for customer installations'],
            ['name' => 'Equipment', 'slug' => 'equipment', 'description' => 'Tools, test equipment, and workshop gear'],
            ['name' => 'Travel', 'slug' => 'travel', 'description' => 'Business travel and mileage'],
            ['name' => 'Fuel', 'slug' => 'fuel', 'description' => 'Vehicle fuel for business use'],
            ['name' => 'Subsistence', 'slug' => 'subsistence', 'description' => 'Food and drink while working'],
            ['name' => 'Utilities', 'slug' => 'utilities', 'description' => 'Business utilities (electricity, broadband, phone)'],
            ['name' => 'Professional Fees', 'slug' => 'professional_fees', 'description' => 'Accountant, legal, and consultancy fees'],
            ['name' => 'Insurance', 'slug' => 'insurance', 'description' => 'Business insurance premiums'],
            ['name' => 'Other', 'slug' => 'other', 'description' => 'Miscellaneous business expenses'],
        ];

        foreach ($categories as $i => $cat) {
            ExpenseCategory::create(array_merge($cat, ['sort_order' => $i]));
        }
    }

    public function down(): void
    {
        ExpenseCategory::whereIn('slug', [
            'stock', 'equipment', 'travel', 'fuel', 'subsistence',
            'utilities', 'professional_fees', 'insurance', 'other',
        ])->delete();
    }
};
