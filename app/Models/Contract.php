<?php

namespace App\Models;

use App\Enum\ContractStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contract extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'folio',
        'supplier_id',
        'company_id',
        'start_date',
        'end_date',
        'contract_amount',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'end_date'        => 'date',
        'cancelled_at'    => 'datetime',
        'contract_amount' => 'decimal:2',
        'status'          => ContractStatus::class,
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ContractProduct::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeEligible($query)
    {
        return $query->where('status', ContractStatus::ACTIVE->value)
            ->whereDate('end_date', '>=', Carbon::today()->toDateString())
            ->whereHas('supplier', fn($q) => $q->where('approval_status', 'approved')->where('is_active', true));
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Lógica de negocio ─────────────────────────────────────────────────

    public function isEligible(): bool
    {
        return $this->status === ContractStatus::ACTIVE
            && $this->end_date->gte(Carbon::today())
            && $this->supplier->approval_status === 'approved'
            && $this->supplier->is_active;
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === ContractStatus::CANCELLED) {
            return 'cancelled';
        }
        if (! $this->end_date) {
            return 'active';
        }
        if ($this->end_date->lt(Carbon::today())) {
            return 'expired';
        }
        return 'active';
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->end_date->startOfDay(), false);
    }

    public function getEffectiveStatusLabelAttribute(): string
    {
        return match($this->effective_status) {
            'cancelled' => 'Cancelado',
            'expired'   => 'Vencido',
            default     => 'Activo',
        };
    }

    public function getEffectiveStatusBadgeAttribute(): string
    {
        return match($this->effective_status) {
            'cancelled' => 'secondary',
            'expired'   => 'warning',
            default     => 'success',
        };
    }

    public static function nextFolio(): string
    {
        $year   = date('Y');
        $prefix = "CONT-{$year}-";
        $last   = static::where('folio', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderBy('folio', 'desc')
            ->value('folio');

        $n = 0;
        if ($last && preg_match('/CONT-\d{4}-(\d+)/', $last, $m)) {
            $n = (int) $m[1];
        }

        return sprintf('%s%03d', $prefix, $n + 1);
    }

    // ── ActivityLog ───────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'supplier_id', 'company_id', 'start_date',
                'end_date', 'status', 'contract_amount',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('contracts');
    }
}
