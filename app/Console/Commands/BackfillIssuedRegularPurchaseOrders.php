<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use Illuminate\Console\Command;

class BackfillIssuedRegularPurchaseOrders extends Command
{
    protected $signature = 'purchase-orders:backfill-issued-regular
                            {--dry-run : Simula la correccion sin escribir cambios}';

    protected $description = 'Corrige OCs regulares legacy atoradas en OPEN y las emite como ISSUED cuando su flujo aguas arriba ya fue aprobado.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se realizaran cambios en la base de datos.');
        }

        $query = PurchaseOrder::query()
            ->with(['quotationSummary.rfq', 'requisition'])
            ->where('status', 'OPEN')
            ->whereNotNull('quotation_summary_id')
            ->whereHas('quotationSummary', fn ($summaryQuery) => $summaryQuery->where('approval_status', 'approved'))
            ->whereHas('quotationSummary.rfq', fn ($rfqQuery) => $rfqQuery->where('status', 'COMPLETED'))
            ->whereHas('requisition', fn ($requisitionQuery) => $requisitionQuery->where('status', 'COMPLETED'))
            ->orderBy('id');

        $eligibleCount = (clone $query)->count();

        if ($eligibleCount === 0) {
            $this->info('No se encontraron OCs regulares elegibles para correccion.');

            return self::SUCCESS;
        }

        $this->info("OCs regulares elegibles para correccion: {$eligibleCount}");

        $updatedCount = 0;

        $query->chunkById(100, function ($orders) use ($dryRun, &$updatedCount) {
            foreach ($orders as $order) {
                $approvedAt = $order->approved_at
                    ?? $order->quotationSummary?->approved_at
                    ?? $order->created_at;

                $issuedAt = $order->issued_at
                    ?? $approvedAt
                    ?? $order->created_at;

                $this->line(sprintf(
                    ' - %s | status=%s | approved_at=%s | issued_at=%s',
                    $order->folio,
                    $order->status,
                    optional($approvedAt)->format('Y-m-d H:i:s') ?? 'NULL',
                    optional($issuedAt)->format('Y-m-d H:i:s') ?? 'NULL'
                ));

                if ($dryRun) {
                    continue;
                }

                $order->forceFill([
                    'status' => 'ISSUED',
                    'approved_at' => $approvedAt,
                    'issued_at' => $issuedAt,
                ])->save();

                $updatedCount++;
            }
        });

        if ($dryRun) {
            $this->info('Dry-run completado.');

            return self::SUCCESS;
        }

        $this->info("OCs actualizadas a ISSUED: {$updatedCount}");

        return self::SUCCESS;
    }
}
