<?php

namespace App\Modules\Attendance\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id',
        'recorded_at',
        'count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
