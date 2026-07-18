<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualBackup extends Model
{
    protected $fillable = [
        'filename',
        'disk',
        'backup_name',
        'created_by_user_id',
        'notes',
    ];

    public function creator(): BelongsTo
    {
        $this->belongsTo(User::class, 'created_by_user_id');
    }
}
