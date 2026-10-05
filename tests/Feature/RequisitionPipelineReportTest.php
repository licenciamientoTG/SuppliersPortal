<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\RequisitionStatusHistory;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionPipelineReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_traceability_report_shows_stalled_stage_pending_owner_and_cycle_hours(): void
    {
        $requisition = Requisition::factory()->create([
            'status' => 'PENDING',
            'created_at' => now()->subHours(8),
            'updated_at' => now()->subHours(4),
        ]);
        RequisitionStatusHistory::create([
            'requisition_id' => $requisition->id,
            'from_status' => 'DRAFT',
            'to_status' => 'PENDING',
            'event_type' => 'STATUS_CHANGED',
            'occurred_at' => now()->subHours(4),
        ]);

        $result = app(ReportingService::class)->result('requisition-traceability', [
            'date_from' => now()->startOfYear()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $row = $result['rows']->first();
        $this->assertSame('Validación de Compras', $row->etapa_detenida);
        $this->assertSame('Cola de Compras', $row->aprobador_pendiente);
        $this->assertSame('Por determinar', $row->requiere_repse);
        $this->assertSame('Historial con eventos registrados', $row->cobertura_historial);
        $this->assertGreaterThanOrEqual(3, $row->horas_en_etapa);
        $this->assertGreaterThanOrEqual(7, $row->horas_ciclo);
        $this->assertIsInt($row->horas_en_etapa);
        $this->assertIsInt($row->horas_ciclo);
        $this->assertContains('Etapa detenida', $result['columns']);
        $this->assertSame(1, $result['kpis']['Requisiciones']);
    }
}
