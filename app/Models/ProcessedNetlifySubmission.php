<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedNetlifySubmission extends Model
{
    protected $fillable = [
        'submission_id', 'site_id', 'form_id', 'submission_data',
        'customer_id', 'enquiry_id', 'processed_at',
    ];

    protected $casts = [
        'submission_data' => 'array',
        'processed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
