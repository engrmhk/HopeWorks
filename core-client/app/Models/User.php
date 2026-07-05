<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'church_id',
        'name',
        'email',
        'password',
        'totp_secret',
        'totp_enabled',
        'must_reset_password',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'totp_enabled' => 'boolean',
            'must_reset_password' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function loginHistory(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function isSynodAdmin(): bool
    {
        return $this->hasRole('Synod Admin');
    }

    public function isChurchAdmin(): bool
    {
        return $this->hasRole('Church Admin');
    }

    public function needsPasswordReset(): bool
    {
        return $this->must_reset_password;
    }

    public function needsOnboarding(): bool
    {
        return $this->onboarding_completed_at === null;
    }
}
