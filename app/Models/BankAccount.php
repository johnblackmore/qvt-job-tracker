<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'provider_account_id',
        'name',
        'type',
        'currency',
        'is_active',
        'balance_pence',
        'balance_fetched_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'balance_pence' => 'integer',
        'balance_fetched_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
