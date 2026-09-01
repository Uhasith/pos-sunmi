<?php

namespace App\Policies;

use App\Models\InventoryTransaction;
use App\Models\User;

class InventoryTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function view(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return $user->canManageCatalog();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return false;
    }

    public function delete(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return false;
    }
}
