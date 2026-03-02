<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
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
        $middleware->alias([
            'role' => App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data' => (object) [],
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'data' => (object) [],
                'errors' => ['auth' => ['Debes iniciar sesión.']],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'data' => (object) [],
                'errors' => ['authorization' => [$exception->getMessage()]],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'data' => (object) [],
                'errors' => ['resource' => ['Recurso no encontrado.']],
            ], 404);
        });

        $exceptions->render(function (\Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage() ?: 'HTTP error',
                    'data' => (object) [],
                    'errors' => ['http' => [$exception->getMessage() ?: 'Error HTTP.']],
                ], $exception->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'data' => (object) [],
                'errors' => ['server' => ['Error interno del servidor.']],
            ], 500);
        });
    })->create();
