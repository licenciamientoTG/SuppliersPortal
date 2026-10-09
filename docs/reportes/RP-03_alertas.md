# RP-03 · Alertas de agotamiento, sobregiro y excepciones

Fecha: 2026-10-06 · Rama: `feat/rp03-budget-alerts`

## Decisiones (aprobadas 2026-10-06)

1. **Ritmo de gasto como RP-01:** promedio de lo ejercido (comprometido + devengado) en los últimos
   3 meses cerrados del mismo renglón (centro + cuenta + subcuenta). Meses para agotarse =
   disponible que queda en el año (meses desde el actual) ÷ ritmo. Ya no depende de
   `budget_line_history`, que solo tiene datos desde el 29-sep-2026.
2. **Documentos en trámite:** las cotizaciones en aprobación (`approval_status = pending`) y las OCD
   `PENDING_APPROVAL` ya apartan presupuesto, así que no hay "sobregiro si se aprueban". Se listan
   con su importe y se muestra el "disponible si se rechazan" = disponible + en trámite. Las
   requisiciones sin cotizar no tienen importe ni mes y no se incluyen.
3. **Resumen diario (06:00):** un correo por responsable con todos sus renglones en riesgo (solo si
   tiene) y uno para Contraloría (`BUDGET_ALERTS_CONTROLLER_EMAIL`) con todos. Antes era un correo
   por renglón, todos los días. `php artisan budget:send-daily-alerts --dry-run` muestra a quién se
   enviaría sin mandar nada.
4. **Granularidad mensual:** cada renglón mensual, igual que el bloqueo.

## Qué cambió

- Fuente única: reporte, aviso por evento (`BudgetAlertService`) y resumen diario calculan con
  `BudgetPositionService`. El % de consumo es el mismo de RP-01 (consumido / vigente). La decisión
  de excepciones también usa `getBalanceAmount()`.
- `App\Reports\Budget\BudgetAlertsReport` reemplaza a `BudgetAlertsReportService` (consultas por
  lote en vez de varias por renglón).
- Filtros: ejercicio, umbral 80/90/100, meses, empresas, centros, responsable, fechas y estatus de
  excepciones. Alcance igual que RP-01 (antes también veían el centro los usuarios asignados a él;
  ahora solo el responsable, como pide la especificación).
- Columnas nuevas: nivel de alerta, ritmo, disponible restante del año, meses y mes de agotamiento,
  documentos en trámite (con liga), disponible si se rechazan, excepciones aprobadas del renglón.
- Excepciones: las aprobadas sin autorizador, motivo o fecha se marcan como "Registro incompleto".
  Aprobar/rechazar (Dirección General) usa SweetAlert con comentario.
- Aviso por evento: si un movimiento cruza varios umbrales a la vez se avisa solo el más alto; los
  menores quedan registrados para no repetirse en el mes.
- Exportación con encabezado, celdas tipadas y bitácora (`Exportación RP-03 xlsx|csv`). La escritura
  común de Excel/CSV vive en `App\Reports\Support\ReportSheet` (también la usa RP-01).

## Pendiente / fuera de alcance

- **RA-01 no existe:** el criterio "cuadra contra la bitácora RA-01" no se puede verificar; hoy
  `budget_exceptions` es el registro de las excepciones.
- La base `dev_suppliersPortalDB` tiene pendiente `2026_09_29_000001_create_budget_alerts_and_exceptions_tables`
  (y otras 4); sin ella RP-03 y el registro de historial del observer fallan. Revisar producción.
- Tiempo de respuesta medido en producción el 2026-10-09: menos de 0.5 s.
