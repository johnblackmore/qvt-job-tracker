<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSent extends Model
{
    use HasFactory;

    protected $table = 'emails_sent';

    protected $fillable = [
        'customer_id', 'quote_id', 'order_id', 'template_id',
        'to_email', 'subject', 'body_html', 'postmark_message_id',
        'status', 'sent_at', 'error_message', 'metadata',
        'opened_at', 'clicked_at', 'bounced_at', 'bounce_type',
        'spam_complaint_at', 'delivered_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'metadata' => 'array',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'spam_complaint_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }
}
