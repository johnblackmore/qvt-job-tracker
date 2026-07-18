<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDraftGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'enquiry_id',
        'assistant_name',
        'tone',
        'trigger_source',
        'provider',
        'model',
        'prompt_data',
        'raw_response',
        'summary',
        'draft_subject',
        'draft_body',
        'confidence',
        'suggested_next_steps',
        'knowledge_gaps',
        'status',
        'error_message',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'suggested_next_steps' => 'array',
        'knowledge_gaps' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
