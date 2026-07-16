<?php

namespace App\Models;

use App\Enum\PaymentTerm;
use App\Notifications\ResetPasswordNotification;
use App\Services\SupplierDocumentRequirementService;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Supplier extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'email_verified_at',
        'is_active',
        'last_login',
        'company_name',
        'rfc',
        'address',
        'postal_code',
        'phone_number',
        'contact_person',
        'contact_phone',
        'supplier_type',
        'person_type',
        'tax_regimes',
        'bank_name',
        'account_number',
        'clabe',
        'currency',
        'accepted_currencies',
        'approval_status',
        'document_status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_notes',
        'swift_bic',
        'iban',
        'bank_address',
        'aba_routing',
        'us_bank_name',
        'default_payment_terms',
        'provides_specialized_services',
        'repse_registration_number',
        'repse_expiry_date',
        'specialized_services_types',
        'economic_activity',
        'is_external',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'provides_specialized_services' => 'boolean',
            'repse_expiry_date' => 'date',
            'specialized_services_types' => 'array',
            'tax_regimes' => 'array',
            'economic_activity' => 'array',
            'accepted_currencies' => 'array',
            'is_external' => 'boolean',
        ];
    }

    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('is_external', true);
    }

    public function getNameAttribute(): string
    {
        return $this->full_name ?: $this->company_name;
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getUserAttribute(): ?User
    {
        return null;
    }

    public function getPersonTypeLabelAttribute(): string
    {
        return SupplierFiscalCatalog::personTypeLabel($this->person_type);
    }

    public function getTaxRegimesLabelAttribute(): string
    {
        return SupplierFiscalCatalog::formatRegimes($this->tax_regimes);
    }

    public function getEconomicActivitiesLabelAttribute(): string
    {
        return SupplierFiscalCatalog::formatActivities($this->economic_activity);
    }

    public function getDefaultPaymentTermEnumAttribute(): ?PaymentTerm
    {
        return $this->default_payment_terms
            ? PaymentTerm::tryFrom($this->default_payment_terms)
            : null;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(SupplierDocumentRequirement::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function financialProvisions(): HasMany
    {
        return $this->hasMany(FinancialProvision::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function missingRequiredDocuments(): array
    {
        $requiredTypes = SupplierDocument::requiredTypesFor($this);

        $present = $this->documents()
            ->whereIn('doc_type', $requiredTypes)
            ->pluck('doc_type')
            ->unique()
            ->all();

        return array_values(array_diff($requiredTypes, $present));
    }

    public function requiresRepseRegistration(): bool
    {
        return $this->provides_specialized_services === true;
    }

    public function hasValidRepseRegistration(): bool
    {
        if (! $this->requiresRepseRegistration()) {
            return true;
        }

        return ! empty($this->repse_registration_number)
            && $this->repse_expiry_date
            && $this->repse_expiry_date->isFuture();
    }

    public function repseExpiresIn(): ?int
    {
        if (! $this->repse_expiry_date) {
            return null;
        }

        return (int) now()->diffInDays($this->repse_expiry_date, false);
    }

    public function sirocs(): HasMany
    {
        return $this->hasMany(SupplierSiroc::class);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved' && $this->is_active;
    }

    public function canAccessFullPortal(): bool
    {
        return $this->isApproved() && ! app(SupplierDocumentRequirementService::class)->hasBlockingRequirements($this);
    }

    public function hasRestrictedPortalAccess(): bool
    {
        return ! $this->canAccessFullPortal();
    }

    public function recalculateDocumentStatus(): string
    {
        $requirements = app(SupplierDocumentRequirementService::class)->ensureForSupplier($this);
        $status = match (true) {
            $requirements->contains(fn (SupplierDocumentRequirement $item) => $item->status === 'rejected') => 'rejected',
            $requirements->contains(fn (SupplierDocumentRequirement $item) => $item->status === 'submitted') => 'in_review',
            app(SupplierDocumentRequirementService::class)->hasBlockingRequirements($this) => 'pending',
            default => 'approved',
        };

        $this->forceFill(['document_status' => $status])->saveQuietly();

        return $status;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query
            ->where('approval_status', 'approved')
            ->where('is_active', true);
    }

    public function scopeNotEfos69b(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->from('sat_efos_69b as e')
                ->whereColumn('e.rfc', 'suppliers.rfc')
                ->whereIn('e.situation', ['Definitivo', 'Presunto']);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        return $query->where(function ($qq) use ($term) {
            $qq->where('company_name', 'like', "%{$term}%")
                ->orWhere('rfc', 'like', "%{$term}%");
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getEfosStatusAttribute(): ?string
    {
        return DB::table('sat_efos_69b as e')
            ->where('e.rfc', $this->rfc)
            ->orderByRaw("
                CASE
                  WHEN e.situation = 'Definitivo' THEN 1
                  WHEN e.situation = 'Presunto' THEN 2
                  ELSE 3
                END
            ")
            ->value('situation');
    }

    public function getIsEfosAttribute(): bool
    {
        $status = $this->efos_status;

        return in_array($status, ['Definitivo', 'Presunto'], true);
    }

    protected function getTableColumnsCached(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $connection = $this->getConnection();
        $table = $this->getTable();

        $cols = $connection->table('INFORMATION_SCHEMA.COLUMNS')
            ->select('COLUMN_NAME')
            ->where('TABLE_NAME', $table)
            ->pluck('COLUMN_NAME')
            ->mapWithKeys(fn ($c) => [$c => true])
            ->all();

        return $cache = $cols;
    }

    public function rfqs()
    {
        return $this->belongsToMany(Rfq::class, 'rfq_suppliers')
            ->using(RfqSupplier::class)
            ->withPivot([
                'invited_at',
                'responded_at',
                'quotation_pdf_path',
                'notes',
            ])
            ->withTimestamps();
    }

    public function rfqResponses(): HasManyThrough
    {
        return $this->hasManyThrough(
            RfqResponse::class,
            Rfq::class,
            'supplier_id',
            'rfq_id',
            'id',
            'id'
        );
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
