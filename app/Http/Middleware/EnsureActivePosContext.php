<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePosContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->active) {
            throw new AuthenticationException;
        }

        if ($request->bearerToken() === null) {
            throw new AuthenticationException;
        }

        $token = $user->currentAccessToken();

        if (! $token->can('pos')) {
            throw new AuthenticationException;
        }

        $device = Device::query()
            ->whereKey($token->getAttribute('device_id'))
            ->where('active', true)
            ->first();

        if ($device === null) {
            throw new AuthenticationException;
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
