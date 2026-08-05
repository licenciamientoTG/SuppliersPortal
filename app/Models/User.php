<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'department_id',
        'budget_profile_id',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function budgetProfile(): BelongsTo
    {
        return $this->belongsTo(BudgetProfile::class);
    }

    public function subaccounts(): BelongsToMany
    {
        return $this->belongsToMany(Subaccount::class, 'subaccount_user');
    }

    public function budgetProfiles(): BelongsToMany
    {
        return $this->belongsToMany(BudgetProfile::class, 'budget_profile_user');
    }

    public function authorizerAssignment(): HasOne
    {
        return $this->hasOne(UserAuthorizerRole::class);
    }

    public function sessionActivities(): HasMany
    {
        return $this->hasMany(UserSessionActivity::class);
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

    public function approvalDelegations(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'delegator_user_id');
    }

    public function approvalDelegationMemberships(): HasMany
    {
        return $this->hasMany(ApprovalDelegationMember::class, 'delegate_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->name ?? '');
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
