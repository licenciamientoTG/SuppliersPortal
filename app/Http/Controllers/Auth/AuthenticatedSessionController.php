<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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

        return redirect()->intended(
            $guard === 'supplier'
                ? route('supplier.documents.index', absolute: false)
                : route('dashboard', absolute: false)
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = Auth::guard('supplier')->check() ? 'supplier' : 'web';

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
