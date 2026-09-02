<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->active;
    }

    public function create(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->canManageCatalog();
    }

    public function adjustStock(User $user, Product $product): bool
    {
        return $user->canManageCatalog();
    }

    public function retryWooCommerceStockSync(User $user, Product $product): bool
    {
        return $user->canManageCatalog();
    }
}
