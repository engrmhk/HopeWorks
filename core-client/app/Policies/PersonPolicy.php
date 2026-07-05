<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('people.view') || $user->isChurchAdmin() || $user->isSynodAdmin();
    }

    public function view(User $user, Person $person): bool
    {
        if ($user->isSynodAdmin()) {
            return true;
        }

        return $user->church_id === $person->church_id;
    }

    public function create(User $user): bool
    {
        return $user->can('people.manage') || $user->isChurchAdmin();
    }

    public function update(User $user, Person $person): bool
    {
        if ($user->isSynodAdmin()) {
            return true;
        }

        return ($user->can('people.manage') || $user->isChurchAdmin())
            && $user->church_id === $person->church_id;
    }

    public function delete(User $user, Person $person): bool
    {
        return $this->update($user, $person);
    }
}
