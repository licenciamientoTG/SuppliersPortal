<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCedula;
use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetMovementAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $director;

    private CostCenter $center;

    private ExpenseCategory $category;

    private BudgetCedula $cedula;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('general_director');
        $this->requester = User::factory()->create(['is_active' => true]);
        $this->director = User::factory()->create(['is_active' => true]);
        $this->director->assignRole('general_director');
        BudgetMovementApprovalSetting::create(['director_user_id' => $this->director->id]);
        $this->center = CostCenter::factory()->create(['responsible_user_id' => $this->requester->id]);
        $this->category = ExpenseCategory::factory()->create();
        $this->cedula = BudgetCedula::factory()->create(['expense_category_id' => $this->category->id]);
        AnnualBudget::create(['cost_center_id' => $this->center->id, 'fiscal_year' => now()->year, 'total_annual_amount' => 0, 'status' => 'APROBADO', 'created_by' => $this->director->id]);
    }

    private function payload(): array
    {
        return [
            'movement_type' => 'AMPLIACION', 'fiscal_year' => now()->year, 'movement_date' => now()->toDateString(), 'total_amount' => 500,
            'justification' => 'Ampliación para prueba de adjuntos de soporte.', 'cost_center_id' => $this->center->id, 'month' => 3,
            'expense_category_id' => $this->category->id, 'budget_cedula_id' => $this->cedula->id,
        ];
    }

    public function test_request_stores_support_files_and_only_visible_users_download_them(): void
    {
        Storage::fake('local');
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->payload() + [
            'attachments' => [UploadedFile::fake()->create('soporte.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $movement = BudgetMovement::latest('id')->firstOrFail();
        $attachment = $movement->attachments()->sole();
        $this->assertSame('soporte.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->actingAs($this->requester)->get(route('budget_movements.attachments.download', [$movement, $attachment]))->assertOk()->assertDownload('soporte.pdf');
        $this->actingAs(User::factory()->create())->get(route('budget_movements.attachments.download', [$movement, $attachment]))->assertForbidden();
        $this->actingAs($this->requester)->get(route('budget_movements.show', $movement))->assertSeeText('soporte.pdf');
    }

    public function test_request_without_attachments_is_still_valid(): void
    {
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->payload())->assertRedirect();
        $this->assertSame(0, BudgetMovement::latest('id')->firstOrFail()->attachments()->count());
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->requester)->post(route('budget_movements.store'), $this->payload() + [
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('attachments.0');
        $this->assertSame(0, BudgetMovement::count());
    }
}
