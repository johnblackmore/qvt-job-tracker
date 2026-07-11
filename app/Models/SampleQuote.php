<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SampleQuote extends Model
{
    protected $fillable = ['name', 'description', 'line_items', 'is_active', 'notes'];

    protected $casts = [
        'line_items' => 'array',
        'is_active' => 'boolean',
    ];
}
