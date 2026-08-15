<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ControlPlaneAuditLog extends Model
{
    protected $table = 'control_plane_audit_logs';

    protected $fillable = [
        'actor_id',
        'action',
        'high_visibility',
        'correlation_id',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'high_visibility' => 'boolean',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function scopeHighVisibility($query)
    {
        return $query->where('high_visibility', true);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
