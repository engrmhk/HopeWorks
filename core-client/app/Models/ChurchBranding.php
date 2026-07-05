<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchBranding extends Model
{
    protected $table = 'church_branding';

    protected $fillable = [
        'church_id',
        'logo',
        'favicon',
        'theme_color',
        'login_image',
        'footer_text',
        'contact_info',
    ];

    protected function casts(): array
    {
        return [
            'contact_info' => 'array',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
