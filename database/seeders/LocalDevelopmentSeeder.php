<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'admin@example.com']);
        $admin->name = 'Local Admin';
        $admin->role = UserRole::Admin;
        $admin->active = true;

        if (! $admin->exists) {
            $admin->password = Hash::make('password');
        }

        $admin->save();

        foreach (['Northstar Drinks', 'Sunrise Bakery', 'Harbor Foods'] as $name) {
            Brand::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }

        foreach (['Central Wholesale', 'Fresh Route Supply', 'Metro Distribution'] as $name) {
            Supplier::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
