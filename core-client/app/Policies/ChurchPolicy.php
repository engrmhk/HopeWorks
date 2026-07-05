<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSynodAdmin() || $user->isChurchAdmin();
    }

    public function view(User $user, Church $church): bool
    {
        if ($user->isSynodAdmin()) {
            return true;
        }

        return $user->church_id === $church->id;
    }

    public function create(User $user): bool
    {
        return $user->isSynodAdmin();
    }

    public function update(User $user, Church $church): bool
    {
        if ($user->isSynodAdmin()) {
            return true;
        }

        return $user->isChurchAdmin() && $user->church_id === $church->id;
    }

    public function delete(User $user, Church $church): bool
    {
        return $user->isSynodAdmin();
    }
}
