<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'first_name',
        'last_name',
        'is_active',
        'avatar',
        'phone',
        'job_title',
        'last_login',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relación 1:1 con Supplier
    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'user_id');
    }

    // app/Models/User.php
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function authorizerAssignment(): HasOne
    {
        return $this->hasOne(UserAuthorizerRole::class);
    }

    public function authorizerRole(): HasOneThrough
    {
        return $this->hasOneThrough(
            AuthorizerRole::class,
            UserAuthorizerRole::class,
            'user_id',
            'id',
            'id',
            'authorizer_role_id'
        );
    }

    public function authorizerExceptions(): HasMany
    {
        return $this->hasMany(AuthorizerException::class);
    }

    public function activeAuthorizerException(): HasOne
    {
        return $this->hasOne(AuthorizerException::class)->active()->latestOfMany();
    }

    // Accesor útil para mostrar nombre completo
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->name ?? '');
    }

    public function isSupplier(): bool
    {
        return $this->hasRole('supplier') && $this->supplier()->exists();
    }

    public function supplierStatus(?string $status = null): bool
    {
        $supplier = $this->supplier; // usa relación ya cargada si existe
        if (! $supplier) {
            return false;
        }

        return $status ? $supplier->status === $status : (bool) $supplier->status;
    }

    /**
     * ¿Debe terminar onboarding de proveedor?
     * Regla: usuario con rol supplier y status pending_docs
     */
    public function mustFinishSupplierOnboarding(): bool
    {
        return $this->isSupplier() && $this->supplierStatus('pending_docs');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withTimestamps();
    }

    /**
     * Centros de costo asignados al usuario.
     * Relación many-to-many con tabla pivote cost_center_user
     */
    public function costCenters()
    {
        return $this->belongsToMany(CostCenter::class, 'cost_center_user')
            ->withPivot('is_default', 'is_active', 'created_by', 'updated_by')
            ->withTimestamps()
            ->withTrashed(); // Por si usas soft deletes en cost_center_user
    }

    /**
     * Centros de costo activos del usuario
     */
    public function activeCostCenters()
    {
        return $this->costCenters()->wherePivot('is_active', true);
    }

    /**
     * Centro de costo predeterminado del usuario
     */
    public function defaultCostCenter()
    {
        return $this->costCenters()->wherePivot('is_default', true)->first();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
