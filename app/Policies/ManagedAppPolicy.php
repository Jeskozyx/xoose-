<?php

namespace App\Policies;

use App\Models\ManagedApp;
use App\Models\User;

class ManagedAppPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ManagedApp $managedApp): bool
    {
        return $managedApp->owner_id === null || $managedApp->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ManagedApp $managedApp): bool
    {
        return $managedApp->owner_id === null || $managedApp->owner_id === $user->id;
    }

    public function delete(User $user, ManagedApp $managedApp): bool
    {
        return $managedApp->owner_id === null || $managedApp->owner_id === $user->id;
    }
}
