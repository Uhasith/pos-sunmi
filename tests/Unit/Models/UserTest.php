<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Panel;

it('allows active administrative roles to access Filament', function (UserRole $role) {
    $user = new User;
    $user->role = $role;
    $user->active = true;

    expect($user->canAccessPanel(Panel::make()))->toBeTrue();
})->with([
    UserRole::Manager,
    UserRole::Admin,
]);

it('rejects users without active administrative access', function (UserRole $role, bool $active) {
    $user = new User;
    $user->role = $role;
    $user->active = $active;

    expect($user->canAccessPanel(Panel::make()))->toBeFalse();
})->with([
    'cashier' => [UserRole::Cashier, true],
    'inactive manager' => [UserRole::Manager, false],
    'inactive admin' => [UserRole::Admin, false],
]);
