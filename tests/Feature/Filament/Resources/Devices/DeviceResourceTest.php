<?php

use App\Enums\UserRole;
use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Models\Device;
use App\Models\User;
use Filament\Actions\CreateAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('allows active managers and administrators to manage devices', function (UserRole $role, bool $active, bool $allowed) {
    $user = User::factory()->withRole($role)->create(['active' => $active]);
    $device = Device::factory()->create();

    expect($user->can('viewAny', Device::class))->toBe($allowed)
        ->and($user->can('create', Device::class))->toBe($allowed)
        ->and($user->can('update', $device))->toBe($allowed)
        ->and($user->can('delete', $device))->toBeFalse();
})->with([
    'active manager' => [UserRole::Manager, true, true],
    'active administrator' => [UserRole::Admin, true, true],
    'cashier' => [UserRole::Cashier, true, false],
    'inactive administrator' => [UserRole::Admin, false, false],
]);

it('creates a pre-provisioned device without accepting last seen data', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());

    Livewire::test(ManageDevices::class)
        ->callAction(CreateAction::class, data: [
            'uuid' => 'dc527228-4e9b-4419-b2d1-e3333ba06966',
            'name' => 'Front counter',
            'active' => true,
            'last_seen_at' => now(),
        ])
        ->assertHasNoFormErrors();

    expect(Device::query()->sole())
        ->uuid->toBe('dc527228-4e9b-4419-b2d1-e3333ba06966')
        ->name->toBe('Front counter')
        ->active->toBeTrue()
        ->last_seen_at->toBeNull();
});
