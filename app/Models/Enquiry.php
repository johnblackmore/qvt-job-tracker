<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'email', 'phone', 'from_name', 'source', 'status', 'subject', 'message',
        'internal_notes', 'responded_at', 'staff_user_id',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class)->orderBy('created_at');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(EnquiryReply::class)->latestOfMany();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(EnquiryActivityLog::class)->orderByDesc('created_at');
    }
}
