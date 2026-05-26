<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\NotificationCenterService;
use App\Services\ModuleAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate; // 👈 AGREGAR ESTA LÍNEA
use Illuminate\Validation\Rules\Password;
use App\Models\ExchangeRate;
use App\Models\SupplierDocument;
use App\Models\ReceivingLocation; // 👈 AGREGAR ESTA LÍNEA
use App\Policies\ReceivingLocationPolicy; // 👈 AGREGAR ESTA LÍNEA

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers()->symbols();
        });

        // 👇 REGISTRAR LA POLICY PARA RECEIVINGLOCATION
        Gate::policy(ReceivingLocation::class, ReceivingLocationPolicy::class);

        Blade::if('moduleAccess', function (string $module) {
            return app(ModuleAccessService::class)->userCanAccessModule(request()->user(), $module);
        });

        // Inyectar el número de documentos pendientes en el sidebar
        View::composer('layouts.partials.sidebar', function ($view) {
            try {
                $pendingCount = SupplierDocument::where('status', 'pending_review')->count();
            } catch (\Throwable $e) {
                // Si aún no se han ejecutado migraciones, evita que truene
                $pendingCount = 0;
            }

            $view->with('pendingReviewCount', $pendingCount);
        });

        View::composer('layouts.partials.navbar', function ($view) {
            $user = request()->user();

            if (! $user) {
                $view->with('recentNotifications', collect())
                    ->with('unreadNotificationsCount', 0)
                    ->with('exchangeRate', null);

                return;
            }

            $notificationCenter = app(NotificationCenterService::class);

            $view->with(
                'recentNotifications',
                rescue(fn () => $notificationCenter->recentForUser($user, 6), collect())
            );

            $view->with(
                'unreadNotificationsCount',
                rescue(fn () => $notificationCenter->unreadCountForUser($user), 0)
            );

            $view->with(
                'exchangeRate',
                rescue(
                    fn () => Cache::remember('exchange_rate_usd_mxn_current', 300, fn () => ExchangeRate::current('USD', 'MXN')),
                    null
                )
            );
        });

        // Compartir siempre la variable para evitar excepciones en vistas que la esperan.
        View::share('exchangeRate', null);
    }
}
