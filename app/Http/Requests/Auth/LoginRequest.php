<?php

namespace App\Http\Requests\Auth;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $remember = $this->boolean('remember');
        $guard = $this->resolveGuardForEmail((string) $this->input('email'));

        if (! $guard || ! Auth::guard($guard)->attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Las cuentas internas y de proveedor comparten la misma sesión HTTP.
        // Evitamos que ambas guardas queden autenticadas al alternar de portal.
        $otherGuard = $guard === 'supplier' ? 'web' : 'supplier';
        Auth::guard($otherGuard)->logout();

        Auth::shouldUse($guard);

        $authenticatable = Auth::guard($guard)->user();
        if (! $authenticatable->is_active) {
            Auth::guard($guard)->logout();

            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está inactiva. Contacta al administrador.',
            ]);
        }

        $this->session()->put('auth.guard', $guard);

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function resolveGuardForEmail(string $email): ?string
    {
        $normalized = Str::lower(trim($email));

        if (User::query()->whereRaw('LOWER(email) = ?', [$normalized])->exists()) {
            return 'web';
        }

        if (Supplier::query()->whereRaw('LOWER(email) = ?', [$normalized])->exists()) {
            return 'supplier';
        }

        return null;
    }
}
