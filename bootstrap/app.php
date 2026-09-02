<?php

use App\Exceptions\CheckoutConflict;
use App\Exceptions\InvalidWooCommerceWebhook;
use App\Exceptions\InventoryIdempotencyConflict;
use App\Exceptions\WooCommerceWebhookConflict;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AssignRequestId::class]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $details = null;

            if ($exception instanceof ValidationException) {
                $code = 'validation_failed';
                $message = 'The given data was invalid.';
                $details = $exception->errors();
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } elseif ($exception instanceof AuthenticationException) {
                $code = 'unauthenticated';
                $message = 'Authentication is required.';
                $status = Response::HTTP_UNAUTHORIZED;
            } elseif ($exception instanceof AuthorizationException) {
                $code = 'forbidden';
                $message = 'This action is not authorized.';
                $status = Response::HTTP_FORBIDDEN;
            } elseif ($exception instanceof InvalidWooCommerceWebhook) {
                $code = 'invalid_woocommerce_webhook';
                $message = 'The WooCommerce webhook could not be authenticated.';
                $status = Response::HTTP_UNAUTHORIZED;
            } elseif ($exception instanceof WooCommerceWebhookConflict) {
                $code = 'woocommerce_webhook_conflict';
                $message = 'The WooCommerce delivery ID was reused with different data.';
                $status = Response::HTTP_CONFLICT;
            } elseif ($exception instanceof CheckoutConflict) {
                $code = $exception->errorCode;
                $message = $exception->getMessage();
                $details = $exception->details;
                $status = Response::HTTP_CONFLICT;
            } elseif ($exception instanceof InventoryIdempotencyConflict) {
                $code = 'conflict';
                $message = 'The idempotency key was already used with different data.';
                $status = Response::HTTP_CONFLICT;
            } elseif ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === Response::HTTP_FORBIDDEN) {
                $code = 'forbidden';
                $message = 'This action is not authorized.';
                $status = Response::HTTP_FORBIDDEN;
            } elseif ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                $code = 'not_found';
                $message = 'The requested resource was not found.';
                $status = Response::HTTP_NOT_FOUND;
            } elseif ($exception instanceof ThrottleRequestsException) {
                $code = 'rate_limited';
                $message = 'Too many requests.';
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($exception instanceof HttpExceptionInterface) {
                $code = 'request_failed';
                $message = 'The request could not be completed.';
                $status = $exception->getStatusCode();
            } else {
                $code = 'server_error';
                $message = 'An unexpected server error occurred.';
                $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            }

            $requestId = $request->attributes->get('request_id');
            $requestId = is_string($requestId) ? $requestId : (string) Str::uuid();

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                    'request_id' => $requestId,
                ],
            ], $status, ['X-Request-ID' => $requestId]);
        });
    })->create();
