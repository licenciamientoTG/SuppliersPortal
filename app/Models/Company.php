<?php

namespace App\Models;

use App\Support\SupplierFiscalCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'rfc',
        'tax_regime',
        'fiscal_street',
        'fiscal_exterior_number',
        'fiscal_interior_number',
        'fiscal_neighborhood',
        'fiscal_municipality',
        'fiscal_state',
        'fiscal_postal_code',
        'locale',
        'timezone',
        'currency_code',
        'phone',
        'email',
        'domain',
        'website',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Usuarios asignados a esta compañía.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Estaciones de servicio de esta compañía.
     */
    public function stations()
    {
        return $this->hasMany(Station::class);
    }

    /**
     * Centros de costo que pertenecen a esta compañía.
     */
    public function costCenters()
    {
        return $this->hasMany(CostCenter::class);
    }

    /**
     * Centros de costo activos de esta compañía.
     */
    public function activeCostCenters()
    {
        return $this->costCenters()->where('is_active', true);
    }

    /**
     * Scope: empresas visibles para un usuario (todas en las que está dado de alta).
     */
    public function scopeVisibleTo($query, User $user)
    {
        // Si manejas roles con Spatie y quieres superadmin libre, descomenta:
        // if ($user->hasRole('super-admin')) return $query;

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    /**
     * Regímenes fiscales SAT seleccionables para la empresa, indexados por clave.
     *
     * @return array<string, string>
     */
    public static function taxRegimeOptions(): array
    {
        $options = [];

        foreach (SupplierFiscalCatalog::taxRegimes() as $regime) {
            $options[$regime['code']] ??= $regime['label'];
        }

        ksort($options);

        return $options;
    }

    /** Clave y descripción del régimen fiscal, p. ej. "601 · General de Ley Personas Morales". */
    public function taxRegimeLabel(): ?string
    {
        if (blank($this->tax_regime)) {
            return null;
        }

        $label = self::taxRegimeOptions()[$this->tax_regime] ?? null;

        return $label ? $this->tax_regime.' · '.$label : $this->tax_regime;
    }

    /**
     * Domicilio fiscal en dos líneas para el membrete:
     * calle, números y colonia; luego municipio, estado y C.P.
     *
     * @return array<int, string>
     */
    public function fiscalAddressLines(): array
    {
        $street = collect([
            collect([$this->fiscal_street, $this->fiscal_exterior_number])->filter()->implode(' '),
            filled($this->fiscal_interior_number) ? 'Int. '.$this->fiscal_interior_number : null,
            $this->fiscal_neighborhood,
        ])->filter()->implode(', ');

        $locality = collect([
            collect([$this->fiscal_municipality, $this->fiscal_state])->filter()->implode(', '),
            filled($this->fiscal_postal_code) ? 'C.P. '.$this->fiscal_postal_code : null,
        ])->filter()->implode(' · ');

        return array_values(array_filter([$street, $locality]));
    }
}
