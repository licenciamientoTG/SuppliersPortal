<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\UserSessionActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $guard = $request->session()->get('auth.guard', 'web');

        if ($guard === 'web' && Auth::guard('web')->user() instanceof User) {
            UserSessionActivity::updateOrCreate(
                ['session_id' => $request->session()->getId()],
                [
                    'user_id' => Auth::id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 65535),
                    'started_at' => now(),
                    'ended_at' => null,
                ]
            );
        }

        return redirect()->intended(
            $guard === 'supplier'
                ? route('supplier.documents.index', absolute: false)
                : route('dashboard', absolute: false)
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $userId = Auth::guard('web')->id();

        if ($userId) {
            UserSessionActivity::query()
                ->where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
        }

        // Una sesión puede conservar credenciales de ambas guardas si se cambió
        // entre portal interno y proveedor. Cerrar ambas evita redirecciones cíclicas.
        Auth::guard('web')->logout();
        Auth::guard('supplier')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
