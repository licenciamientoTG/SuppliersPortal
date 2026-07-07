<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ReceivingLocation - Representa una ubicación física donde se reciben bienes y servicios
 * 
 * Este modelo gestiona las diferentes ubicaciones de recepción como:
 * - Estaciones de servicio (gasolineras)
 * - Oficinas corporativas
 * - Almacenes y bodegas
 * 
 * Relaciones:
 * - Tiene muchas órdenes de compra (PurchaseOrder)
 * - Pertenece a muchos usuarios (User) a través de tabla pivote
 * 
 * @property int $id
 * @property string $code Código único de la ubicación
 * @property string $name Nombre descriptivo
 * @property string $type Tipo de ubicación (service_station, corporate, warehouse, other)
 * @property string|null $address Dirección completa
 * @property string|null $city Ciudad
 * @property string|null $state Estado o provincia
 * @property string $country País (por defecto México)
 * @property string|null $postal_code Código postal
 * @property string|null $phone Teléfono de contacto
 * @property string|null $email Correo electrónico de contacto
 * @property string|null $manager_name Responsable de la ubicación
 * @property bool $is_active Estado activo/inactivo
 * @property bool $portal_blocked Indica si el portal está bloqueado (FRU 4.5)
 * @property string|null $notes Observaciones
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ReceivingLocation extends Model
{
    use HasFactory;

    /**
     * Tipos de ubicación permitidos según el catálogo del FRU
     */
    public const TYPE_SERVICE_STATION = 'service_station';
    public const TYPE_CORPORATE = 'corporate';
    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_OTHER = 'other';

    /**
     * Array con todos los tipos disponibles para validación
     */
    public const TYPES = [
        self::TYPE_SERVICE_STATION,
        self::TYPE_CORPORATE,
        self::TYPE_WAREHOUSE,
        self::TYPE_OTHER,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Los atributos que son asignables masivamente
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'company_id',
        'name',
        'type',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'manager_name',
        'is_active',
        'portal_blocked',
        'notes',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'portal_blocked' => 'boolean',
        'company_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Los atributos por defecto del modelo
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'country' => 'México',
        'type' => self::TYPE_SERVICE_STATION,
        'is_active' => true,
        'portal_blocked' => false,
    ];

    /**
     * RELACIONES
     */

    /**
     * Obtiene las órdenes de compra asociadas a esta ubicación
     * Una ubicación puede tener muchas órdenes de compra pendientes de recibir
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'receiving_location_id');
    }


    /**
     * Obtiene los usuarios autorizados para recibir en esta ubicación
     * Relación muchos a muchos con la tabla pivote receiving_location_user
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'receiving_location_user')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }

    /**
     * ALIYAS DE RELACIONES (métodos con nombres en español para consistencia)
     */

    /**
     * Alias de purchaseOrders() - Órdenes de compra de esta ubicación
     */
    public function ordenesCompra(): HasMany
    {
        return $this->purchaseOrders();
    }

    /**
     * Recepciones registradas en esta ubicación
     */
    public function receptions(): HasMany
    {
        return $this->hasMany(Reception::class);
    }

    /**
     * Alias de receptions() - Recepciones realizadas en esta ubicación
     */
    public function recepciones(): HasMany
    {
        return $this->receptions();
    }

    /**
     * Alias de users() - Usuarios autorizados en esta ubicación
     */
    public function usuariosAutorizados(): BelongsToMany
    {
        return $this->users();
    }

    /**
     * SCOPES - Consultas reutilizables
     */

    /**
     * Scope para filtrar ubicaciones activas
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, int|string|null $companyId)
    {
        return $companyId
            ? $query->where('company_id', (int) $companyId)
            : $query;
    }

    /**
     * Scope para filtrar ubicaciones con portal bloqueado
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePortalBlocked($query)
    {
        return $query->where('portal_blocked', true);
    }

    /**
     * Scope para filtrar ubicaciones con portal desbloqueado
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePortalUnblocked($query)
    {
        return $query->where('portal_blocked', false);
    }

    /**
     * Scope para filtrar por tipo de ubicación
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para filtrar estaciones de servicio
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeServiceStations($query)
    {
        return $query->where('type', self::TYPE_SERVICE_STATION);
    }

    /**
     * Scope para filtrar ubicaciones corporativas
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCorporate($query)
    {
        return $query->where('type', self::TYPE_CORPORATE);
    }

    /**
     * Scope para filtrar almacenes
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWarehouses($query)
    {
        return $query->where('type', self::TYPE_WAREHOUSE);
    }

    /**
     * Scope para filtrar por ciudad
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $city
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    /**
     * Scope para filtrar por estado
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $state
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope para buscar por código o nombre (búsqueda rápida)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('code', 'LIKE', "%{$search}%")
              ->orWhere('name', 'LIKE', "%{$search}%")
              ->orWhere('city', 'LIKE', "%{$search}%")
              ->orWhere('manager_name', 'LIKE', "%{$search}%");
        });
    }

    /**
     * ACCESORES Y MUTADORES
     */

    /**
     * Accesor: Obtiene el nombre del tipo en formato legible
     * 
     * @return string
     */
    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            self::TYPE_SERVICE_STATION => 'Estación de Servicio',
            self::TYPE_CORPORATE => 'Corporativo',
            self::TYPE_WAREHOUSE => 'Almacén',
            self::TYPE_OTHER => 'Otro',
            default => 'Desconocido',
        };
    }

    /**
     * Accesor: Obtiene la ubicación completa formateada
     * 
     * @return string
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Accesor: Obtiene el estado del portal como texto
     * 
     * @return string
     */
    public function getPortalStatusAttribute(): string
    {
        return $this->portal_blocked ? 'Bloqueado' : 'Disponible';
    }

    /**
     * Accesor: Obtiene el estado general como texto
     * 
     * @return string
     */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'Activa' : 'Inactiva';
    }

    /**
     * Accesor: Obtiene badge HTML para el estado
     * 
     * @return string
     */
    public function getStatusBadgeAttribute(): string
    {
        $class = $this->is_active ? 'badge bg-success' : 'badge bg-danger';
        $text = $this->is_active ? 'Activa' : 'Inactiva';
        
        return "<span class='{$class}'>{$text}</span>";
    }

    /**
     * Accesor: Obtiene badge HTML para el estado del portal
     * 
     * @return string
     */
    public function getPortalBadgeAttribute(): string
    {
        if ($this->portal_blocked) {
            return '<span class="badge bg-danger">Portal Bloqueado</span>';
        }
        
        return '<span class="badge bg-success">Portal Disponible</span>';
    }

    /**
     * MÉTODOS PERSONALIZADOS
     */

    /**
     * Bloquea el portal de proveedores para esta ubicación
     * Implementa FRU punto 4.5 - CA-028
     * 
     * @return bool
     */
    public function blockPortal(): bool
    {
        return $this->update(['portal_blocked' => true]);
    }

    /**
     * Desbloquea el portal de proveedores para esta ubicación
     * Implementa FRU punto 4.5 - CA-030
     * 
     * @return bool
     */
    public function unblockPortal(): bool
    {
        return $this->update(['portal_blocked' => false]);
    }

    /**
     * Verifica si un usuario está autorizado para recibir en esta ubicación
     * 
     * @param User $user
     * @return bool
     */
    public function isUserAuthorized(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Obtiene los usuarios autorizados con su rol de receptor estándar
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStandardReceivers()
    {
        return $this->users()->wherePivot('is_default', false)->get();
    }

    /**
     * Obtiene los aprobadores de esta ubicación (con permiso de recepción masiva)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApprovers()
    {
        // Asumiendo que tienes un rol/spatie permissions o campo en la tabla pivote
        // Este es un placeholder - ajusta según tu sistema de permisos
        return $this->users()->wherePivot('is_approver', true)->get();
    }


    /**
     * Verifica si esta ubicación tiene OCs en estado DELIVERED_PENDING_RECEPTION.
     * Si hay al menos una, NINGÚN proveedor puede registrar nuevas entregas aquí.
     */
    public function hasDeliveryPendingReception(): bool
    {
        $hasPO = PurchaseOrder::where('receiving_location_id', $this->id)
            ->where('status', 'DELIVERED_PENDING_RECEPTION')
            ->exists();

        if ($hasPO) {
            return true;
        }

        return DirectPurchaseOrder::where('receiving_location_id', $this->id)
            ->where('status', 'DELIVERED_PENDING_RECEPTION')
            ->exists();
    }

    /**
     * Obtiene las OCs en estado DELIVERED_PENDING_RECEPTION para esta ubicación.
     * Útil para mostrar al proveedor cuáles OCs están bloqueando.
     */
    public function getOrdersPendingReception()
    {
        $pos = PurchaseOrder::where('receiving_location_id', $this->id)
            ->where('status', 'DELIVERED_PENDING_RECEPTION')
            ->with('supplier')
            ->get();

        $dpos = DirectPurchaseOrder::where('receiving_location_id', $this->id)
            ->where('status', 'DELIVERED_PENDING_RECEPTION')
            ->with('supplier')
            ->get();

        return $pos->merge($dpos);
    }

    /**
     * VALIDACIÓN
     */

    /**
     * Reglas de validación para el modelo
     * 
     * @return array
     */
    public static function validationRules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:receiving_locations,code',
            'company_id' => 'required|integer|exists:companies,id',
            'name' => 'required|string|max:100',
            'type' => 'required|in:' . implode(',', self::TYPES),
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'manager_name' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'portal_blocked' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Mensajes de validación personalizados
     * 
     * @return array
     */
    public static function validationMessages(): array
    {
        return [
            'code.required' => 'El código de ubicación es obligatorio',
            'code.unique' => 'Este código de ubicación ya está registrado',
            'name.required' => 'El nombre de la ubicación es obligatorio',
            'type.required' => 'El tipo de ubicación es obligatorio',
            'type.in' => 'El tipo de ubicación seleccionado no es válido',
            'email.email' => 'El correo electrónico debe ser válido',
        ];
    }
}
