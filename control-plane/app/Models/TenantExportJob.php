<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantExportJob extends Model
{
    protected $fillable = [
        'scope',
        'scope_id',
        'requested_by',
        'status',
        'file_path',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
