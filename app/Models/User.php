<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PERMISSION_RECORD_MANUAL_PAYMENT = 'record-manual-payment';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'permissions',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions, true);
    }

    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];

        if (! in_array($permission, $permissions, true)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }
}
