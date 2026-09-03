<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAuthTokenRequest;
use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthTokenController extends Controller
{
    public function store(StoreAuthTokenRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', Str::lower($request->string('email')->toString()))
            ->first();

        if ($user === null || ! $user->active || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $device = Device::query()
            ->where('uuid', $request->string('device_uuid')->toString())
            ->where('active', true)
            ->first();

        if ($device === null) {
            throw ValidationException::withMessages([
                'device_uuid' => ['The device is not active.'],
            ]);
        }

        $newToken = $user->createToken($device->name, ['pos']);
        $newToken->accessToken->forceFill(['device_id' => $device->id])->save();
        $device->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'data' => [
                'token' => $newToken->plainTextToken,
                'user' => $this->userData($user),
                'device' => $this->deviceData($device),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $request->attributes->get('device');

        if (! $user instanceof User || ! $device instanceof Device) {
            throw new AuthenticationException;
        }

        return response()->json([
            'data' => [
                'user' => $this->userData($user),
                'device' => $this->deviceData($device),
            ],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        $token->delete();

        return response()->noContent();
    }

    /** @return array{id: int, name: string, email: string, role: string} */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }

    /** @return array{uuid: string, name: string, printer: array{model: ?string, ip_address: ?string, port: ?int, paper_width: ?int, configured: bool}} */
    private function deviceData(Device $device): array
    {
        return [
            'uuid' => $device->uuid,
            'name' => $device->name,
            'printer' => [
                'model' => $device->printer_model,
                'ip_address' => $device->printer_ip_address,
                'port' => $device->printer_port,
                'paper_width' => $device->printer_paper_width,
                'configured' => $device->hasPrinterConfiguration(),
            ],
        ];
    }
}
