<?php

namespace App\Providers;

use App\Models\ExchangeRate;
use App\Models\ReceivingLocation;
use App\Models\SupplierDocument;
use App\Models\Requisition;
use App\Observers\RequisitionObserver;
use App\Policies\ReceivingLocationPolicy;
use App\Policies\SupplierDocumentPolicy;
use App\Services\ComplianceDocumentQrExtractor;
use App\Services\DocumentIssueDateExtractionService;
use App\Services\ModuleAccessService;
use App\Services\NotificationCenterService;
use Illuminate\Support\Facades\Blade; // 👈 AGREGAR ESTA LÍNEA
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider; // 👈 AGREGAR ESTA LÍNEA
use Illuminate\Validation\Rules\Password; // 👈 AGREGAR ESTA LÍNEA

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DocumentIssueDateExtractionService::class, fn ($app) => new DocumentIssueDateExtractionService([
            $app->make(ComplianceDocumentQrExtractor::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Requisition::observe(RequisitionObserver::class);
        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers()->symbols();
        });

        // 👇 REGISTRAR LA POLICY PARA RECEIVINGLOCATION
        Gate::policy(ReceivingLocation::class, ReceivingLocationPolicy::class);
        Gate::policy(SupplierDocument::class, SupplierDocumentPolicy::class);

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
            $user = request()->user('supplier') ?? request()->user('web') ?? request()->user();

            if (! $user) {
                $view->with('recentNotifications', collect())
                    ->with('unreadNotificationsCount', 0)
                    ->with('exchangeRate', null);

                return;
            }

            $notificationCenter = app(NotificationCenterService::class);
            $notificationCenter->resolveObsoleteUnreadForUser($user);

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
