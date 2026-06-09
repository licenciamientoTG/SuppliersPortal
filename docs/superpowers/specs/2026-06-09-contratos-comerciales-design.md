# Spec: Módulo de Contratos Comerciales

**Fecha:** 2026-06-09
**Portal:** SuppliersPortal
**Autor:** aochoaTG
**Estado:** Aprobado

---

## Objetivo

Registrar contratos marco con proveedores para productos específicos que no requieren cotización por cada compra. El módulo permite gestionar contratos, vincularlos con requisiciones directas, y mantener trazabilidad completa de cambios y compras realizadas.

---

## Decisiones de diseño tomadas

- **Precio fijo por producto en contrato** — `unit_price` almacenado en `contract_products`. La partida de requisición copia ese precio como snapshot al momento de crearse. El precio del contrato puede editarse posteriormente; las requisiciones ya generadas conservan su snapshot sin afectarse.
- **Sin flujo de aprobación del contrato** — Buyer/Compras crea el contrato y queda activo de inmediato.
- **Proveedor activo** determinado por `suppliers.status = 'activo'`.
- **Componente Livewire separado** (`ContractRequisitionForm`) para requisiciones por contrato. No se modifica el `RequisitionForm` existente. Cualquier usuario con acceso a requisiciones puede crear una requisición por contrato.
- **Estado `expired` es calculado**, no almacenado: `end_date < today AND status = active`. Evita jobs de sincronización y condiciones de carrera.
- **Folio `CONT-YYYY-NNN`** — consecutivo global (todos los contratos), reinicia en 001 cada año calendario.
- **OC automática al hacer submit de requisición por contrato** — se generan OCs directamente agrupadas por proveedor, sin pasar por cotización/RFQ.

---

## 1. Modelo de Datos

### Tabla nueva: `contracts`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `folio` | varchar | Formato `CONT-YYYY-NNN`, autoincremental |
| `supplier_id` | FK → `suppliers` | Proveedor activo |
| `company_id` | FK → `companies` | Empresa (no centro de costo) |
| `start_date` | date | |
| `end_date` | date | |
| `contract_amount` | decimal(14,2) | DEFAULT 0. Solo informativo, sin restricciones. |
| `status` | enum(`active`, `cancelled`) | `expired` es calculado, no almacenado |
| `cancellation_reason` | text nullable | |
| `cancelled_by` | FK → `users` nullable | |
| `cancelled_at` | datetime nullable | |
| `created_by` | FK → `users` | |
| `updated_by` | FK → `users` | |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### Tabla nueva: `contract_products`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `contract_id` | FK → `contracts` | |
| `product_service_id` | FK → `products_services` | |
| `unit_price` | decimal(14,4) | Precio fijo del contrato |
| `currency_code` | varchar(3) | Default `MXN` |
| `unit_of_measure` | varchar | Heredado del producto, overrideable |
| `notes` | text nullable | Condiciones especiales del ítem |
| `created_at` | datetime | |
| `updated_at` | datetime | |
| UNIQUE | `(contract_id, product_service_id)` | Un producto una vez por contrato |

### Cambios en tablas existentes

**`requisitions`** — agregar columna:
- `source_type` enum(`rfq`, `contract`) DEFAULT `rfq`

**`requisition_items`** — agregar columnas:
- `contract_id` FK → `contracts` nullable
- `contract_product_id` FK → `contract_products` nullable
- `unit_price` decimal(14,4) nullable — snapshot del precio al crear la partida
- `currency_code` varchar(3) nullable

### Diagrama de relaciones

```
companies ──────────────────────────────────────┐
suppliers ──────────────────────┐               │
                                ▼               ▼
                           contracts ───────────┘
                                │
                    ┌───────────┴──────────┐
                    ▼                      ▼
            contract_products         activity_log
            (product_service_id)      (subject: contracts)
                    │
                    ▼
          requisition_items
          (contract_id, contract_product_id, unit_price snapshot)
                    │
                    ▼
              requisitions
              (source_type: 'contract')
```

---

## 2. Flujos de Trabajo

### Flujo A: Crear contrato

1. Buyer accede a `/contracts/create`
2. Selecciona empresa, proveedor activo, fechas, monto (opcional)
3. Agrega uno o más productos del catálogo activo con precio unitario y unidad
4. Validaciones al guardar (ver Sección 3)
5. Guarda `contracts` + `contract_products` en transacción
6. ActivityLog: evento `created`

### Flujo B: Cancelar contrato

1. Buyer accede al detalle del contrato → botón "Cancelar"
2. Modal con campo de motivo de cancelación (requerido)
3. Solo permitido si `status = active` (no ya cancelado ni vencido)
4. Actualiza: `status = cancelled`, `cancelled_by`, `cancelled_at`, `cancellation_reason`
5. ActivityLog: evento `cancelled` con old/new status
6. Requisiciones ya generadas **no se afectan** — solo bloquea nuevas

### Flujo C: Carga masiva (CSV/Excel)

1. Buyer descarga la plantilla desde `/contracts/template` — CSV con encabezados y hoja de instrucciones
2. Llena el layout:
   - Columnas: `empresa_code`, `supplier_rfc`, `start_date`, `end_date`, `contract_amount`, `product_code`, `unit_price`, `currency`
   - Un contrato = múltiples filas (una por producto), agrupadas por `empresa+proveedor+fechas`
3. Sube el archivo a `/contracts/import` → `importPreview` (no guarda nada)
4. Sistema valida por fila y muestra tabla de resultados con semáforo (✓/✗)
5. Deduplicación: si el mismo `empresa+proveedor+fechas` aparece más de una vez en el layout **o** ya existe en BD, se marca como error
6. Resumen: "Se crearán X contratos. Y filas tienen errores y serán omitidas."
7. Botón "Confirmar importación" habilitado solo si ≥ 1 fila válida
8. `importConfirm` guarda solo filas válidas en transacción por contrato
9. ActivityLog: evento `bulk_imported` por cada contrato creado
10. Límite: 500 filas por archivo. Para volúmenes mayores, usar job en cola con notificación por email.

### Flujo D: Crear requisición por contrato

1. Requisitor elige "Nueva requisición por contrato" → `ContractRequisitionForm`
2. Captura: empresa, fecha requerida, ubicación de recepción
3. Agrega partidas:
   - Selecciona contrato activo (filtrado por empresa y elegibilidad)
   - Selecciona producto del contrato → precio se autocompleta (read-only)
   - Captura: cantidad, centro de costo, categoría de gasto, cédula presupuestal
   - Distintos contratos pueden coexistir en la misma requisición
4. Re-validación al hacer submit (ver Sección 3)
5. Guarda `requisition` (source_type=`contract`) + `requisition_items` con snapshot de precio
6. Flujo posterior: al hacer submit se generan OCs automáticamente agrupadas por proveedor (una OC por proveedor con sus productos), **sin pasar por cotización/RFQ**

---

## 3. Lógica de Negocio y Validaciones

### Método `Contract::isEligible()`

```php
public function isEligible(): bool
{
    return $this->status === 'active'
        && $this->end_date >= Carbon::today()
        && $this->supplier->status === 'activo';
}
```

### Accessor `Contract::getEffectiveStatusAttribute()`

```php
public function getEffectiveStatusAttribute(): string
{
    if ($this->status === 'cancelled') return 'cancelled';
    if ($this->end_date < Carbon::today()) return 'expired';
    return 'active';
}
```

### Scope `Contract::scopeEligible()`

```php
public function scopeEligible($query)
{
    return $query->where('status', 'active')
        ->whereDate('end_date', '>=', Carbon::today()->toDateString())
        ->whereHas('supplier', fn($q) => $q->where('status', 'activo'));
}
```

> **Nota SQL Server:** usar `Carbon::today()->toDateString()` (no `today()` de Laravel) para garantizar formato `YYYY-MM-DD` compatible.

### Tabla de validaciones

| Regla | Momento | Tipo |
|---|---|---|
| `supplier.status = 'activo'` | Crear contrato, submit requisición | Backend + JS |
| `company.is_active = true` | Crear contrato | Backend |
| `end_date > start_date` | Crear/editar contrato | JS inmediato + backend |
| Producto activo (`is_active + status=ACTIVE`) | Agregar producto al contrato | Backend |
| Sin duplicado `(contract_id, product_service_id)` | Agregar producto | JS inmediato + unique constraint BD |
| Contrato elegible al hacer submit | Submit de requisición | Backend (re-check) |
| Proveedor activo al hacer submit | Submit de requisición | Backend (re-check) |
| Producto existe en el contrato seleccionado | Submit de requisición | Backend |
| No duplicar contrato en import | Carga masiva preview | Backend |

### `ContractRequisitionForm` — acciones Livewire

| Acción | Descripción |
|---|---|
| `mount()` | Carga empresas del usuario, ubicaciones de recepción |
| `updatedCompanyId()` | Filtra contratos elegibles por empresa |
| `updatedContractId($index)` | Carga productos del contrato seleccionado |
| `addItem()` | Valida elegibilidad, copia `unit_price` snapshot |
| `removeItem($index)` | Elimina partida del array local |
| `submit()` | Re-valida todo, guarda en transacción DB |

---

## 4. Estrategia de Auditoría

Usar **Spatie Laravel ActivityLog** (ya instalado). No se requiere tabla adicional.

### Eventos registrados en `activity_log`

| Acción | `log_name` | `event` | `properties` |
|---|---|---|---|
| Crear contrato | `contracts` | `created` | Todos los fillables |
| Editar contrato | `contracts` | `updated` | Solo campos cambiados: `{old, new}` |
| Cancelar contrato | `contracts` | `cancelled` | `{old: {status}, new: {status}, reason}` |
| Agregar producto | `contracts` | `product_added` | `{product_service_id, unit_price, currency_code}` |
| Quitar producto | `contracts` | `product_removed` | `{product_service_id}` |
| Importación masiva | `contracts` | `bulk_imported` | `{created: N, skipped: M, source: "archivo.csv"}` |

### Configuración en `Contract`

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly(['supplier_id', 'company_id', 'start_date',
                   'end_date', 'status', 'contract_amount'])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->useLogName('contracts');
}
```

### Vista de historial

- Tab "Historial" en el detalle del contrato: lista `activity_log` filtrada por `subject_type = Contract` y `subject_id`.
- Tab "Compras realizadas": query sobre `requisitions` con `source_type = 'contract'` + join a `requisition_items.contract_id`. Filtros: rango de fechas, producto, monto.

---

## 5. Consideraciones de UX

### Badges de estado efectivo

| Estado | Badge | Ícono Tabler |
|---|---|---|
| `active` | verde | `file-check` |
| `expired` | naranja | `clock-x` |
| `cancelled` | gris oscuro | `file-x` |

### Mensajes y alertas

**En formulario de contrato:**
- `end_date < hoy` → advertencia inline amarilla: *"La fecha de fin está en el pasado. El contrato quedará vencido al guardar."*
- Al cancelar → modal de confirmación con motivo requerido.
- Producto ya en contrato → error inline JS inmediato: *"Este producto ya está en el contrato."*

**En requisición por contrato:**
- Contrato vence en ≤ 30 días → banner amarillo: *"Este contrato vence el DD/MM/YYYY. Verifica con Compras antes de continuar."*
- Contrato vencido → select deshabilitado + texto rojo: *"Contrato vencido. No disponible para nuevas requisiciones."*
- Proveedor inactivado al hacer submit → error: *"El proveedor [nombre] ya no está activo. Selecciona otro contrato."*

**En carga masiva:**
- Enlace de descarga de plantilla visible con tooltip.
- Tabla de revisión con semáforo por fila antes de confirmar.
- Botón "Confirmar importación" deshabilitado si todas las filas tienen error.

**En detalle de requisición:**
- Mostrar contrato vinculado por partida con link al detalle del contrato.
- Si `unit_price` snapshot difiere del precio actual en el contrato → badge: *"Precio actualizado en contrato"*.

### Navegación

- Sección "Contratos" en menú lateral, visible solo para roles `buyer`/`compras`.
- En página de inicio de requisiciones: card "Nueva requisición por contrato" que lleva a `ContractRequisitionForm`.

---

## 6. Riesgos y Puntos de Atención

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|---|
| 1 | Estado del contrato cambia mientras el form está abierto | Media | Alto | Re-validar `isEligible()` en `submit()` dentro de la transacción |
| 2 | Timeout en carga masiva con archivos grandes | Baja | Medio | Límite de 500 filas. Volúmenes mayores: job en cola + notificación por email |
| 3 | Precio snapshot desactualizado sin aviso al usuario | Media | Medio | Comparar snapshot vs. precio actual del contrato; badge de aviso si difieren |
| 4 | `effective_status` calculado en PHP no disponible en queries SQL | Alta (error frecuente) | Alto | `scopeEligible` usa `whereDate()` directamente en SQL, nunca el accessor |
| 5 | Formato de fechas en SQL Server | Media | Medio | Usar `Carbon::today()->toDateString()` en todos los scopes de fecha |
| 6 | Producto dado de baja después de firmar contrato | Baja | Bajo | Marcar en rojo productos inactivos en el detalle del contrato |

---

## 7. Orden de Implementación

### Fase 1 — Backend base y CRUD de contratos (~3–4 días)
1. Migraciones: `contracts`, `contract_products`, columnas en `requisitions` y `requisition_items`
2. Modelos `Contract` y `ContractProduct`: relaciones, scopes, `isEligible()`, `getEffectiveStatusAttribute()`, `nextFolio()`
3. Enum `ContractStatus` (`active`, `cancelled`)
4. `ContractController`: index, create, store, show, edit, update, cancel
5. Vistas Blade con validaciones JS inline
6. ActivityLog en modelo `Contract`

### Fase 2 — Carga masiva (~2 días)
1. `ContractImportService`: parseo, validación, agrupación, deduplicación
2. Layout descargable (CSV + instrucciones)
3. Rutas `importPreview` y `importConfirm`
4. Vista de resultados con tabla semáforo

### Fase 3 — Integración con requisiciones (~3 días)
1. Livewire `ContractRequisitionForm`
2. Cascada: empresa → contratos elegibles → productos → precio snapshot
3. Re-validación en submit
4. Generación automática de OCs por proveedor al hacer submit (sin RFQ)
5. Avisos UX: vencimiento próximo, proveedor inactivo

### Fase 4 — Historial y reportes (~1–2 días)
1. Tab "Historial de cambios" en detalle del contrato
2. Tab "Compras realizadas" con filtros
3. Badge de precio desactualizado en detalle de requisición

**Total estimado: ~9–11 días.** Fases 1 y 3 son secuenciales. Fases 2 y 4 son independientes y paralelizables.
