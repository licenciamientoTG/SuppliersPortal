<?php

namespace App\Console\Commands;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Notifications\DirectPurchaseOrderClosedByInactivityNotification;
use App\Notifications\DirectPurchaseOrderInactivityWarningNotification;
use App\Notifications\PurchaseOrderClosedByInactivityNotification;
use App\Notifications\PurchaseOrderInactivityWarningNotification;
use App\Services\BudgetAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseInactivePurchaseOrders extends Command
{
    protected $signature = 'purchase-orders:close-inactive
                            {--dry-run : Simula el proceso sin hacer cambios}';

    protected $description = 'Cierra automaticamente las OC (directas y estandar) que superaron el plazo de inactividad y envia alertas preventivas a las proximas a vencer.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $now = now();

        if ($dryRun) {
            $this->warn('Modo dry-run: no se realizaran cambios en la base de datos.');
        }

        $this->info('=== Cierre automatico por inactividad ('.$now->format('d/m/Y H:i').') ===');
        $this->info('');
        $this->info('Procesando Ordenes de Compra Directas...');
        $this->processDirectPurchaseOrders($now, $dryRun);

        $this->info('');
        $this->info('Procesando Ordenes de Compra Estandar...');
        $this->processStandardPurchaseOrders($now, $dryRun);

        $this->info('');
        $this->info('Proceso completado.');

        return Command::SUCCESS;
    }

    private function processDirectPurchaseOrders($now, bool $dryRun): void
    {
        $inactivityDays = DirectPurchaseOrder::INACTIVITY_DAYS;
        $warningThreshold = $inactivityDays - 3;

        $closedCount = 0;
        DirectPurchaseOrder::where('status', 'PENDING_APPROVAL')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $now->copy()->subDays($inactivityDays))
            ->with(['assignedApprover', 'creator'])
            ->chunk(200, function ($ocds) use ($now, $dryRun, &$closedCount) {
                foreach ($ocds as $ocd) {
                    $this->closeDirectPurchaseOrder($ocd, $now, $dryRun);
                    $closedCount++;
                }
            });

        if ($closedCount === 0) {
            $this->line('  - No hay OCD vencidas para cerrar.');
        } else {
            $this->line("  - Cerrando {$closedCount} OCD vencida(s)...");
        }

        $alertCount = 0;
        DirectPurchaseOrder::where('status', 'PENDING_APPROVAL')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $now->copy()->subDays($warningThreshold))
            ->where('submitted_at', '>', $now->copy()->subDays($inactivityDays))
            ->whereNull('inactivity_warning_sent_at')
            ->with(['assignedApprover', 'creator'])
            ->chunk(200, function ($ocds) use ($dryRun, &$alertCount) {
                foreach ($ocds as $ocd) {
                    $this->sendDirectPurchaseOrderWarning($ocd, $dryRun);
                    $alertCount++;
                }
            });

        if ($alertCount === 0) {
            $this->line('  - No hay OCD en zona de alerta.');
        } else {
            $this->line("  - Enviando alerta preventiva a {$alertCount} OCD...");
        }
    }

    private function closeDirectPurchaseOrder(DirectPurchaseOrder $ocd, $now, bool $dryRun): void
    {
        $this->line("    -> [OCD] Cerrando: {$ocd->folio} (submitted: {$ocd->submitted_at->format('d/m/Y')})");

        if ($dryRun) {
            return;
        }

        try {
            DB::beginTransaction();

            $assignedApprover = $ocd->assignedApprover;
            $creator = $ocd->creator;

            app(BudgetAllocationService::class)->releaseDirectPurchaseOrder($ocd);

            $ocd->update([
                'status' => 'CLOSED_BY_INACTIVITY',
                'closed_at' => $now,
                'assigned_approver_id' => null,
            ]);

            DB::commit();

            if ($assignedApprover) {
                $assignedApprover->notify(new DirectPurchaseOrderClosedByInactivityNotification($ocd));
            }

            if ($creator) {
                $creator->notify(new DirectPurchaseOrderClosedByInactivityNotification($ocd));
            }

            Log::info("[CloseInactive] OCD {$ocd->folio} (ID: {$ocd->id}) cerrada por inactividad.", [
                'submitted_at' => $ocd->submitted_at,
                'closed_at' => $now,
            ]);

            $this->info("      OK {$ocd->folio} cerrada. Aprobador y solicitante notificados.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[CloseInactive] Error al cerrar OCD {$ocd->folio}: ".$e->getMessage());
            $this->error("      ERROR al cerrar {$ocd->folio}: ".$e->getMessage());
        }
    }

    private function sendDirectPurchaseOrderWarning(DirectPurchaseOrder $ocd, bool $dryRun): void
    {
        $deadline = $ocd->getAutoCloseDeadline();
        $this->line("    -> [OCD] Alerta: {$ocd->folio} - vence el {$deadline?->format('d/m/Y')}");

        if ($dryRun) {
            return;
        }

        try {
            $ocd->update(['inactivity_warning_sent_at' => now()]);

            if ($ocd->assignedApprover) {
                $ocd->assignedApprover->notify(new DirectPurchaseOrderInactivityWarningNotification($ocd));
            }

            if ($ocd->creator) {
                $ocd->creator->notify(new DirectPurchaseOrderInactivityWarningNotification($ocd));
            }

            Log::info("[CloseInactive] Alerta de inactividad enviada para OCD {$ocd->folio}.", [
                'submitted_at' => $ocd->submitted_at,
                'deadline' => $deadline,
            ]);

            $this->info("      OK alerta enviada para {$ocd->folio}.");
        } catch (\Exception $e) {
            Log::error("[CloseInactive] Error al enviar alerta OCD {$ocd->folio}: ".$e->getMessage());
            $this->error("      ERROR al enviar alerta para {$ocd->folio}: ".$e->getMessage());
        }
    }

    private function processStandardPurchaseOrders($now, bool $dryRun): void
    {
        $inactivityDays = PurchaseOrder::INACTIVITY_DAYS;
        $warningThreshold = $inactivityDays - 3;

        $closedCount = 0;
        PurchaseOrder::where('status', 'ISSUED')
            ->whereNotNull('issued_at')
            ->where('issued_at', '<=', $now->copy()->subDays($inactivityDays))
            ->with(['creator', 'supplier', 'requisition'])
            ->chunk(200, function ($pos) use ($now, $dryRun, &$closedCount) {
                foreach ($pos as $po) {
                    $this->closeStandardPurchaseOrder($po, $now, $dryRun);
                    $closedCount++;
                }
            });

        if ($closedCount === 0) {
            $this->line('  - No hay OC estandar vencidas para cerrar.');
        } else {
            $this->line("  - Cerrando {$closedCount} OC estandar vencida(s)...");
        }

        $alertCount = 0;
        PurchaseOrder::where('status', 'ISSUED')
            ->whereNotNull('issued_at')
            ->where('issued_at', '<=', $now->copy()->subDays($warningThreshold))
            ->where('issued_at', '>', $now->copy()->subDays($inactivityDays))
            ->whereNull('inactivity_warning_sent_at')
            ->with(['creator', 'supplier'])
            ->chunk(200, function ($pos) use ($dryRun, &$alertCount) {
                foreach ($pos as $po) {
                    $this->sendStandardPurchaseOrderWarning($po, $dryRun);
                    $alertCount++;
                }
            });

        if ($alertCount === 0) {
            $this->line('  - No hay OC estandar en zona de alerta.');
        } else {
            $this->line("  - Enviando alerta preventiva a {$alertCount} OC estandar...");
        }
    }

    private function closeStandardPurchaseOrder(PurchaseOrder $po, $now, bool $dryRun): void
    {
        $issuedAt = $po->issued_at ?? $po->created_at;

        $this->line("    -> [OC] Cerrando: {$po->folio} (emitida: {$issuedAt->format('d/m/Y')})");

        if ($dryRun) {
            return;
        }

        try {
            DB::beginTransaction();

            $po->update([
                'status' => 'CLOSED_BY_INACTIVITY',
                'closed_at' => $now,
            ]);

            app(BudgetAllocationService::class)->releaseOrder($po);

            DB::commit();

            if ($po->creator) {
                $po->creator->notify(new PurchaseOrderClosedByInactivityNotification($po));
            }

            $requisitionCreator = $po->requisition?->creator ?? null;
            if ($requisitionCreator && $requisitionCreator->id !== $po->creator?->id) {
                $requisitionCreator->notify(new PurchaseOrderClosedByInactivityNotification($po));
            }

            Log::info("[CloseInactive] OC estandar {$po->folio} (ID: {$po->id}) cerrada por inactividad.", [
                'issued_at' => $po->issued_at,
                'closed_at' => $now,
            ]);

            $this->info("      OK {$po->folio} cerrada. Responsables notificados.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[CloseInactive] Error al cerrar OC estandar {$po->folio}: ".$e->getMessage());
            $this->error("      ERROR al cerrar {$po->folio}: ".$e->getMessage());
        }
    }

    private function sendStandardPurchaseOrderWarning(PurchaseOrder $po, bool $dryRun): void
    {
        $deadline = $po->getAutoCloseDeadline();
        $issuedAt = $po->issued_at ?? $po->created_at;

        $this->line("    -> [OC] Alerta: {$po->folio} - emitida {$issuedAt->format('d/m/Y')} - vence el {$deadline->format('d/m/Y')}");

        if ($dryRun) {
            return;
        }

        try {
            $po->update(['inactivity_warning_sent_at' => now()]);

            if ($po->creator) {
                $po->creator->notify(new PurchaseOrderInactivityWarningNotification($po));
            }

            $requisitionCreator = $po->requisition?->creator ?? null;
            if ($requisitionCreator && $requisitionCreator->id !== $po->creator?->id) {
                $requisitionCreator->notify(new PurchaseOrderInactivityWarningNotification($po));
            }

            Log::info("[CloseInactive] Alerta de inactividad enviada para OC estandar {$po->folio}.", [
                'issued_at' => $po->issued_at,
                'deadline' => $deadline,
            ]);

            $this->info("      OK alerta enviada para {$po->folio}.");
        } catch (\Exception $e) {
            Log::error("[CloseInactive] Error al enviar alerta OC {$po->folio}: ".$e->getMessage());
            $this->error("      ERROR al enviar alerta para {$po->folio}: ".$e->getMessage());
        }
    }
}
