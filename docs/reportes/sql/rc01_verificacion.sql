-- RC-01 · Verificación de datos del pipeline de requisiciones (SQL Server, solo lectura).
-- Cada consulta debe devolver 0 filas. Si alguna devuelve filas, el reporte puede mostrar
-- tiempos o motivos incompletos para esas requisiciones.

DECLARE @history_since DATETIME2 = '2026-08-31';

-- 1. Cuadre: horas de ciclo = Σ horas por etapa (redondeadas a 1 decimal por etapa).
--    Replica el cálculo de App\Reports\Purchasing\RequisitionTimeline.
WITH ev AS (
    SELECT h.requisition_id, h.id, h.to_status, h.occurred_at,
           LEAD(h.occurred_at) OVER (PARTITION BY h.requisition_id ORDER BY h.occurred_at, h.id) AS exited_at
    FROM requisition_status_histories h
    WHERE h.event_type IN ('CREATED', 'STATUS_CHANGED')
), steps AS (
    SELECT ev.requisition_id,
           ROUND(DATEDIFF(second, ev.occurred_at,
               COALESCE(ev.exited_at,
                   CASE WHEN ev.to_status IN ('COMPLETED', 'CANCELLED', 'REJECTED') THEN ev.occurred_at ELSE SYSDATETIME() END)
           ) / 3600.0, 1) AS step_hours
    FROM ev
), cycle AS (
    SELECT requisition_id, SUM(step_hours) AS total_cycle_hours, COUNT(*) AS steps
    FROM steps GROUP BY requisition_id
)
SELECT c.requisition_id, c.total_cycle_hours, s.sum_steps
FROM cycle c
JOIN (SELECT requisition_id, SUM(step_hours) AS sum_steps FROM steps GROUP BY requisition_id) s ON s.requisition_id = c.requisition_id
WHERE ROUND(c.total_cycle_hours, 1) <> ROUND(s.sum_steps, 1);

-- 2. Etapas con duración negativa (eventos fuera de orden).
SELECT h.requisition_id, h.id, h.occurred_at, nxt.occurred_at AS next_occurred_at
FROM requisition_status_histories h
CROSS APPLY (
    SELECT TOP 1 n.occurred_at FROM requisition_status_histories n
    WHERE n.requisition_id = h.requisition_id AND n.id > h.id AND n.event_type IN ('CREATED', 'STATUS_CHANGED')
    ORDER BY n.id
) nxt
WHERE h.event_type IN ('CREATED', 'STATUS_CHANGED') AND nxt.occurred_at < h.occurred_at;

-- 3. Huecos del historial: el estatus actual no coincide con el último evento registrado
--    (cambio de estatus que no pasó por Eloquent). Solo requisiciones con historial.
SELECT r.id, r.folio, r.status, last_event.to_status AS last_history_status
FROM requisitions r
CROSS APPLY (
    SELECT TOP 1 h.to_status FROM requisition_status_histories h
    WHERE h.requisition_id = r.id AND h.event_type IN ('CREATED', 'STATUS_CHANGED')
    ORDER BY h.occurred_at DESC, h.id DESC
) last_event
WHERE r.deleted_at IS NULL AND r.created_at >= @history_since AND UPPER(r.status) <> UPPER(last_event.to_status);

-- 4. Requisiciones con historial esperado pero sin eventos.
SELECT r.id, r.folio, r.created_at
FROM requisitions r
WHERE r.deleted_at IS NULL AND r.created_at >= @history_since
  AND NOT EXISTS (SELECT 1 FROM requisition_status_histories h WHERE h.requisition_id = r.id);

-- 5. Rechazadas o canceladas sin motivo.
SELECT r.id, r.folio, r.status
FROM requisitions r
WHERE r.deleted_at IS NULL
  AND ((r.status = 'REJECTED' AND NULLIF(LTRIM(RTRIM(r.rejection_reason)), '') IS NULL)
    OR (r.status = 'CANCELLED' AND NULLIF(LTRIM(RTRIM(r.cancellation_reason)), '') IS NULL));

-- 6. En autorización sin aprobador en la bandeja (sin cotización pendiente con aprobador vigente).
SELECT r.id, r.folio
FROM requisitions r
WHERE r.deleted_at IS NULL AND r.status = 'IN_APPROVAL'
  AND NOT EXISTS (
      SELECT 1 FROM quotation_summaries qs
      WHERE qs.requisition_id = r.id AND qs.deleted_at IS NULL
        AND qs.approval_status = 'pending' AND qs.current_approver_user_id IS NOT NULL);
