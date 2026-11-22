<?php

namespace App\Policies;

use App\Models\AppDevice;
use App\Models\User;

class AppDevicePolicy
{
    public function view(User $user, AppDevice $device): bool
    {
        $ownerId = $device->managedApp?->owner_id;

        return $ownerId === null || $ownerId === $user->id;
    }

    public function control(User $user, AppDevice $device): bool
    {
        return $this->view($user, $device);
    }

    public function revoke(User $user, AppDevice $device): bool
    {
        return $this->view($user, $device);
    }
}
