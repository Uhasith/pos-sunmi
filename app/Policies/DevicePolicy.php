<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function view(User $user, Device $device): bool
    {
        return $user->canManageCatalog();
    }

    public function create(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function update(User $user, Device $device): bool
    {
        return $user->canManageCatalog();
    }

    public function delete(User $user, Device $device): bool
    {
        return false;
    }

    public function restore(User $user, Device $device): bool
    {
        return false;
    }

    public function forceDelete(User $user, Device $device): bool
    {
        return false;
    }
}
