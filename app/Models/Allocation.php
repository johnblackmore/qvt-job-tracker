<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Allocation extends Model
{
    protected $fillable = [
        'allocatable_from_type', 'allocatable_from_id',
        'allocatable_to_type', 'allocatable_to_id',
        'amount', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function allocatableFrom(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocatableTo(): MorphTo
    {
        return $this->morphTo();
    }
}
