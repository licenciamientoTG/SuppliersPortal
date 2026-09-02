<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
// Evento de login que dispara Laravel cuando un usuario inicia sesión
use Illuminate\Auth\Events\Login;
// Nuestro listener que actualizará last_login
use App\Listeners\UpdateLastLogin;

class EventServiceProvider extends ServiceProvider
{
    /**
     * El arreglo $listen mapea "Evento => [Listeners]".
     * Cada vez que ocurra Login::class, se ejecutará UpdateLastLogin::class
     */
    protected $listen = [
        Login::class => [
            UpdateLastLogin::class,
        ],
    ];

    /**
     * Si usas auto-discovery de eventos/listeners no necesitas más.
     * En caso contrario, este método se queda tal cual.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Indica si se debe descubrir automáticamente eventos/listeners.
     * Lo dejamos en false porque ya los registramos explícitamente en $listen.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
