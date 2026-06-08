<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class, 'id', 'id')->whereRaw('1 = 0');
    }

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

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->name ?? '');
    }

    public function isSupplier(): bool
    {
        return false;
    }

    public function supplierStatus(?string $status = null): bool
    {
        return false;
    }

    public function mustFinishSupplierOnboarding(): bool
    {
        return false;
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withTimestamps();
    }

    public function costCenters()
    {
        return $this->belongsToMany(CostCenter::class, 'cost_center_user')
            ->withPivot('is_default', 'is_active', 'created_by', 'updated_by')
            ->withTimestamps()
            ->withTrashed();
    }

    public function activeCostCenters()
    {
        return $this->costCenters()->wherePivot('is_active', true);
    }

    public function defaultCostCenter()
    {
        return $this->costCenters()->wherePivot('is_default', true)->first();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
