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

it('creates a device with network printer settings', function () {
    actingAs(User::factory()->withRole(UserRole::Manager)->create());

    Livewire::test(ManageDevices::class)
        ->callAction(CreateAction::class, data: [
            'uuid' => '4e6591b9-874a-4f30-9e6f-86edb848fe28',
            'name' => 'Front counter',
            'printer_model' => 'Epson TM-T88VI',
            'printer_ip_address' => '192.168.1.50',
            'printer_port' => 9100,
            'printer_paper_width' => 80,
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $device = Device::query()->sole();

    expect($device->printer_model)->toBe('Epson TM-T88VI')
        ->and($device->printer_ip_address)->toBe('192.168.1.50')
        ->and($device->printer_port)->toBe(9100)
        ->and($device->printer_paper_width)->toBe(80)
        ->and($device->hasPrinterConfiguration())->toBeTrue();
});

it('rejects invalid network printer settings', function (array $printerSettings, string $field) {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());

    Livewire::test(ManageDevices::class)
        ->callAction(CreateAction::class, data: [
            'uuid' => '83730210-c427-41ba-ab8d-ab3d4c3a6afb',
            'name' => 'Front counter',
            ...$printerSettings,
            'active' => true,
        ])
        ->assertHasFormErrors([$field]);

    expect(Device::query()->doesntExist())->toBeTrue();
})->with([
    'invalid IP address' => [['printer_ip_address' => 'not-an-ip'], 'printer_ip_address'],
    'port below range' => [['printer_port' => 0], 'printer_port'],
    'port above range' => [['printer_port' => 65536], 'printer_port'],
    'unsupported paper width' => [['printer_paper_width' => 60], 'printer_paper_width'],
]);
