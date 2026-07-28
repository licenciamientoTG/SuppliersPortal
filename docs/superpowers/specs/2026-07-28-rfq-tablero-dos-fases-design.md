# Diseño: Tablero de Cotización de dos fases (RFQ)

**Fecha:** 2026-07-28
**Estado:** Aprobado en ideación; pendiente plan de implementación
**Estrategia:** Coexistencia con el wizard actual — el tablero es una segunda línea de trabajo; el wizard original no se modifica y se elimina solo si el tablero prueba ser mejor a nivel usuario.

## Problema

El flujo de cotización actual (`QuotationWizard`, 5 pasos lineales) es funcional pero tedioso: demasiadas pantallas para requisiciones pequeñas o recurrentes, y el wizard lineal fuerza a todos los grupos de cotización a un solo estado global aunque avancen a ritmos distintos (problema ya parchado en `determineCurrentStep()`). Además, la captura manual de precios por el comprador —clave para compras recurrentes con precio conocido— existe pero está escondida en un modal del paso 3.

## Solución

Una sola pantalla Livewire (`QuotationBoard`) con dos modos, donde la unidad de trabajo es el **grupo de cotización** con ciclo de vida independiente: `Armado → Con precios → Adjudicado`.

### Validación técnica (absorbe paso 1)

Banner colapsable superior con los 3 checks actuales (`specs_clear`, `time_feasible`, `alternatives_evaluated`). Hasta firmarse, las tarjetas de grupo son visibles pero bloqueadas para enviar/adjudicar (armar grupos sí está permitido). Al firmar: mismo efecto que hoy (estado `IN_QUOTATION`, notificación al requisitor), y el banner colapsa a una línea con fecha y responsable.

### Modo Preparación (absorbe pasos 2, 3 y 4)

- Panel lateral: partidas sin agrupar, con drag & drop y checkboxes (mecánica actual del paso 2).
- Área principal: tarjetas de grupo. Cada tarjeta muestra sus partidas y dos caminos al mismo nivel:
  1. **Solicitar cotización** — proveedores + deadline + notas, con botón "Enviar ahora" que crea la RFQ y la envía en una sola acción (fusión de los pasos 3 y 4).
  2. **Capturar precio conocido** — el modal manual existente (`saveManualQuote`), promovido a acción principal, con **memoria de precios**: pre-llenado desde el `RfqResponse` más reciente del mismo producto (proveedor, precio, IVA, días de entrega), mostrando la fecha de la referencia para juzgar vigencia.
- Pistas proactivas por tarjeta: "N de M partidas tienen precio de hace menos de 30 días".

### Adjudicación directa

Cuando un grupo tiene precio capturado para **todas** sus partidas, la tarjeta ofrece "Adjudicar directo", sin exigir invitación a proveedores, mínimo de comparativas ni umbral de monto (decisión explícita del usuario). Trazabilidad: `entry_source = 'buyer_manual'`, `entered_by`, y la referencia de precio usada. Un grupo puede mezclar precio capturado con proveedores invitados; todo compite en la misma comparativa.

### Modo Seguimiento (absorbe paso 5)

Las tarjetas con RFQ enviada migran aquí con su progreso de respuestas (unifica las DataTables de los pasos 4 y 5). Comparación y adjudicación por grupo, sin esperar a los demás grupos.

## Arquitectura de coexistencia

- **Componente nuevo:** `App\Livewire\Rfq\QuotationBoard` + `resources/views/livewire/rfq/quotation-board.blade.php`, ruta `/rfq/board/{requisition}` (nombre `rfq.board`), mismo middleware `module.access:quotations`.
- **`QuotationWizard` no se toca** en su comportamiento. Ambos componentes leen/escriben el mismo estado (modelos `Requisition`, `QuotationGroup`, `Rfq`, `RfqResponse`), así que una requisición trabajada en un flujo se ve coherente en el otro — el wizard ya deriva su paso desde los datos.
- **Servicios compartidos:** las acciones idénticas en ambos flujos se extraen de `QuotationWizard` a clases de servicio (p. ej. `App\Services\Rfq\`): creación/actualización de RFQs con proveedores, cotización manual, generación de folio, adjudicación. El wizard pasa a delegar en ellas (refactor sin cambio de comportamiento); el tablero las consume y añade las capacidades nuevas (memoria de precios, enviar-ahora, adjudicación directa), que solo el tablero expone.
- **Punto de entrada:** en `rfq-index`, junto al botón "Cotizar" actual, botón "Tablero (beta)" hacia la ruta nueva.
- **Retiro del wizard:** si el tablero se valida con usuarios, eliminar el wizard = borrar componente, vistas de pasos y ruta; los servicios quedan.

## Lo que NO cambia

Modelos y esquema de base de datos, folios, notificaciones, flujo del portal del proveedor, y el wizard actual completo. No hay migraciones.

## Manejo de errores

Mismo patrón actual: transacciones DB en acciones compuestas (crear+enviar RFQ, adjudicación), mensajes flash en fallos, validaciones Livewire existentes del modal manual reutilizadas vía servicio.

## Pruebas

- Feature tests de los servicios extraídos (comportamiento idéntico al actual del wizard como línea base).
- Tests del tablero: firma de validación bloquea/desbloquea acciones; enviar-ahora crea y envía en una acción; memoria de precios pre-llena desde el `RfqResponse` correcto (más reciente por producto); adjudicación directa solo disponible con todas las partidas cotizadas.
- Test de interoperabilidad: requisición avanzada en el tablero se abre coherente en el wizard (y viceversa).
