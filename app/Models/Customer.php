<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes'];

    protected static function booted(): void
    {
        static::deleting(function (Customer $customer) {
            if ($customer->isForceDeleting()) {
                return;
            }
            $customer->quotes->each->delete();
            $customer->orders->each->delete();
            $customer->vehicles->each->delete();
            $customer->enquiries->each->delete();
        });
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
