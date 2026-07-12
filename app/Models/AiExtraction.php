<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assistant_name',
        'source_url',
        'prompt_data',
        'raw_response',
        'extracted_data',
        'status',
        'error_message',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'raw_response' => 'json',
        'extracted_data' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
