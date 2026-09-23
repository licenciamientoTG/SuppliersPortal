<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = strtolower(trim((string) $value));
                    $currentEmail = strtolower((string) $this->user()->email);

                    if ($normalized !== $currentEmail
                        && in_array($normalized, config('db_backups.allowed_emails', []), true)) {
                        $fail('Este correo está reservado y no puede asignarse desde el perfil.');
                    }
                },
            ],
        ];
    }
}
