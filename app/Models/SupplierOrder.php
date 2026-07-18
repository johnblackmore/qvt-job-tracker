<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number', 'supplier_id', 'order_date', 'invoice_date', 'invoice_number',
        'due_date', 'subtotal', 'vat_total', 'total_amount', 'currency', 'status',
        'payment_method', 'payment_reference', 'paid_at', 'bank_transaction_id',
        'notes', 'created_by_user_id', 'metadata',
    ];

    protected $casts = [
        'order_date' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(SupplierOrderLineItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class, 'documentable_id')
            ->where('documentable_type', self::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'allocatable_from_id')
            ->where('allocatable_from_type', SupplierOrderLineItem::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
