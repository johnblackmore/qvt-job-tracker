<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExpenseDocument extends Model
{
    protected $fillable = [
        'documentable_type', 'documentable_id', 'file_path', 'original_filename',
        'mime_type', 'file_size', 'document_type', 'ai_extraction_id', 'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function aiExtraction(): BelongsTo
    {
        return $this->belongsTo(AiExtraction::class);
    }

    public function getStoragePath(): string
    {
        return storage_path('app/private/'.$this->file_path);
    }

    public function getUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return route('expenses.documents.download', $this);
    }
}
