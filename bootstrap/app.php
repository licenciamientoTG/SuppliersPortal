<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(function (Request $request): string {
            return $request->user('supplier')
                ? route('supplier.dashboard', absolute: false)
                : route('dashboard', absolute: false);
        });

        // ✅ Todos los alias en UNA SOLA llamada
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'lock' => \App\Http\Middleware\CheckLockScreen::class,
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'module.access' => \App\Http\Middleware\ModuleAccess::class,
            'supplier.approved' => \App\Http\Middleware\EnsureSupplierIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu sesión expiró. Inicia sesión nuevamente.',
                ], 419);
            }

            return redirect()->route('login')
                ->with('status', 'Tu sesión expiró. Inicia sesión nuevamente.');
        });
    })->create();
