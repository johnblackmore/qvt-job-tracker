<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id', 'staff_user_id', 'direction', 'subject', 'body',
        'to_email', 'from_email', 'from_name', 'status',
        'message_id', 'in_reply_to', 'postmark_message_id',
        'ai_draft_data', 'sent_at',
    ];

    protected $casts = [
        'ai_draft_data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
