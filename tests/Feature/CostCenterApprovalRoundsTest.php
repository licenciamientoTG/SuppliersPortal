<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\CostCenterApprovalStep;
use App\Models\QuotationSummary;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Services\AuthorizerResolutionService;
use App\Services\CostCenterApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Reiniciar la autorización de un documento abre una ronda nueva y conserva la
 * cadena de pasos de la ronda anterior en lugar de borrarla.
 */
class CostCenterApprovalRoundsTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;

    private QuotationSummary $summary;

    private CostCenterApprovalFlowService $flow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approver = User::factory()->create();

        $resolver = Mockery::mock(AuthorizerResolutionService::class);
        $resolver->shouldReceive('resolveForRequester')->andReturn(['approver_user' => $this->approver]);
        $this->app->instance(AuthorizerResolutionService::class, $resolver);

        $requisition = Requisition::factory()->create();
        RequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'cost_center_id' => CostCenter::factory()->create(['responsible_user_id' => User::factory()->create()->id])->id,
        ]);
        $this->summary = QuotationSummary::factory()->create(['requisition_id' => $requisition->id, 'total' => 500]);
        $this->flow = app(CostCenterApprovalFlowService::class);
    }

    public function test_reinitializing_keeps_the_previous_round_as_superseded(): void
    {
        $this->assertTrue($this->flow->initialize($this->summary));
        $this->assertTrue($this->flow->initialize($this->summary->fresh()));

        $all = CostCenterApprovalStep::query()->orderBy('id')->get();

        $this->assertCount(2, $all);
        $this->assertSame([1, 2], $all->pluck('round')->map(fn ($round) => (int) $round)->all());
        $this->assertSame(['SUPERSEDED', 'PENDING'], $all->pluck('status')->all());
    }

    public function test_only_the_current_round_drives_the_approval(): void
    {
        $this->flow->initialize($this->summary);
        $this->flow->initialize($this->summary->fresh());

        $summary = $this->summary->fresh();

        $this->assertSame(1, $summary->approvalSteps()->count());
        $this->assertSame(2, (int) $summary->approvalSteps()->first()->round);
        $this->assertSame(2, $summary->approvalStepHistory()->count());
        $this->assertEquals($this->approver->id, $summary->current_approver_user_id);

        $this->assertTrue($this->flow->advance($summary, $this->approver, 'Autorizado.'));
        $this->assertSame('APPROVED', $summary->approvalSteps()->first()->status);
        $this->assertSame('SUPERSEDED', $summary->approvalStepHistory()->where('round', 1)->first()->status);
    }
}
