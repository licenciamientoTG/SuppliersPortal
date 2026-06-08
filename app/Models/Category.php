<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Category
 *
 * Catálogo de categorías de centros de costo.
 * Ejemplos: ADMINISTRACION, ENPROYECTO, STAFF, CORPORATIVO, OPERACIONES, ESTACIONES.
 *
 * Notas de diseño:
 * - Ligero: solo los campos mínimos para operar.
 */
class Category extends Model
{
    use HasFactory;

    // Tabla explícita (opcional; por convención sería 'categories' de todos modos).
    protected $table = 'categories';

    // Campos asignables en masa (mass-assignment) con snake_case.
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    // Casts para tipado consistente al leer/escribir.
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones futuras (ejemplo):
    // public function costCenters()
    // {
    //     return $this->hasMany(CostCenter::class);
    // }
}
