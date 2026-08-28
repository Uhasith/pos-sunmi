<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\LocalDevelopmentSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds reusable local catalog data and an administrator', function () {
    $this->seed(LocalDevelopmentSeeder::class);
    $this->seed(LocalDevelopmentSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->active)->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue();

    $this->assertDatabaseCount('brands', 3);
    $this->assertDatabaseCount('suppliers', 3);
});
