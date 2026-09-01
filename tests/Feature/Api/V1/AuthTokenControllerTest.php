<?php

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;

it('issues a POS token for an active user and pre-provisioned device', function () {
    $user = User::factory()->withRole(UserRole::Cashier)->create([
        'email' => 'cashier@example.com',
    ]);
    $device = Device::factory()->create([
        'uuid' => '4ac52a31-e3fa-4f59-b805-9166b93c6834',
        'name' => 'Front register',
    ]);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'cashier@example.com',
        'password' => 'password',
        'device_uuid' => $device->uuid,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.role', 'cashier')
        ->assertJsonPath('data.device.uuid', $device->uuid)
        ->assertJsonPath('data.device.name', 'Front register');
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
    expect($device->refresh()->last_seen_at)->not->toBeNull();
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'device_id' => $device->id,
        'name' => 'Front register',
    ]);

    $this->withToken($response->json('data.token'))
        ->getJson(route('api.v1.me'))
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.device.uuid', $device->uuid);
});

it('returns 422 with the shared error envelope when login fields are missing', function () {
    $requestId = '76fe81d5-8458-43c6-8dbb-c9784d14a717';

    $this->withHeader('X-Request-ID', $requestId)
        ->postJson(route('api.v1.auth.login'))
        ->assertUnprocessable()
        ->assertHeader('X-Request-ID', $requestId)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.request_id', $requestId)
        ->assertJsonStructure([
            'error' => [
                'details' => ['email', 'password', 'device_uuid'],
            ],
        ]);
});

it('returns 422 and creates no token for invalid credentials', function () {
    User::factory()->create(['email' => 'cashier@example.com']);
    $device = Device::factory()->create();

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'cashier@example.com',
        'password' => 'incorrect-password',
        'device_uuid' => $device->uuid,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.email.0', 'The provided credentials are incorrect.');

    $this->assertDatabaseEmpty('personal_access_tokens');
});

it('returns 422 and creates no token for an inactive device', function () {
    $user = User::factory()->create(['email' => 'cashier@example.com']);
    $device = Device::factory()->inactive()->create();

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_uuid' => $device->uuid,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.device_uuid.0', 'The device is not active.');

    $this->assertDatabaseEmpty('personal_access_tokens');
});

it('returns 401 when a token user becomes inactive', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $user->forceFill(['active' => false])->save();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.me'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('returns 401 when a token device becomes inactive', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $device->forceFill(['active' => false])->save();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.me'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.auth.logout'))
        ->assertNoContent();

    $this->assertDatabaseEmpty('personal_access_tokens');
});
