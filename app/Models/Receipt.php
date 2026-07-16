<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_transaction_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'notes',
        'monzo_attachment_id',
        'sync_status',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function getStoragePath(): string
    {
        return storage_path('app/'.$this->file_path);
    }

    public function getUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return route('admin.banking.receipts.download', $this);
    }

    public function scopePendingSync($query)
    {
        return $query->whereNull('monzo_attachment_id')
            ->where('sync_status', 'pending');
    }

    public function scopeFailedSync($query)
    {
        return $query->where('sync_status', 'failed');
    }
}
