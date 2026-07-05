<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchAffiliationHistory extends Model
{
    protected $table = 'church_affiliation_history';

    protected $fillable = [
        'church_id',
        'synod_id',
        'start_date',
        'end_date',
        'reason',
        'changed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function synod(): BelongsTo
    {
        return $this->belongsTo(Synod::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
