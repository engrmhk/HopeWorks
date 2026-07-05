<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasCustomFields;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use BelongsToChurch;
    use HasCustomFields;
    use HasFactory;

    protected $fillable = [
        'church_id',
        'first_name',
        'last_name',
    ];

    public function getCustomFieldEntityType(): string
    {
        return 'person';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }
}
