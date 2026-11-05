<?php

namespace App\Console\Commands;

use App\Models\DirectPurchaseOrder;
use Illuminate\Console\Command;

/**
 * Hasta 2026-11-04 la aprobación final de una OCD no registraba issued_at, por lo
 * que las OCD emitidas quedaban fuera de reportes que filtran por esa fecha.
 * Este comando solo llena issued_at vacío con approved_at (reconstrucción: en la
 * OCD approved_at solo se asigna en la aprobación final, que es la emisión); no
 * cambia estados, no toca updated_at ni borra datos.
 *
 * CANCELLED y RETURNED se incluyen porque se puede cancelar o devolver una OCD ya
 * emitida; las que nunca se emitieron no tienen approved_at y se omiten.
 */
class BackfillDirectPurchaseOrderIssuedAt extends Command
{
    protected $signature = 'direct-purchase-orders:backfill-issued-at
                            {--dry-run : Muestra las OCD que se corregirían sin escribir cambios}';

    protected $description = 'Llena issued_at de las OCD ya emitidas que no lo tienen, usando su fecha de aprobación.';

    private const CANDIDATE_STATUSES = ['ISSUED', 'DELIVERED_PENDING_RECEPTION', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CANCELLED', 'RETURNED'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se realizarán cambios en la base de datos.');
        }

        $query = DirectPurchaseOrder::query()
            ->whereIn('status', self::CANDIDATE_STATUSES)
            ->whereNull('issued_at')
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No hay OCD emitidas sin issued_at.');

            return self::SUCCESS;
        }

        $this->info("OCD emitidas sin issued_at: {$total}");

        $updated = 0;
        $skipped = 0;

        $query->chunkById(100, function ($orders) use ($dryRun, &$updated, &$skipped) {
            foreach ($orders as $order) {
                if (! $order->approved_at) {
                    $this->warn(" - {$order->folio} | status={$order->status} | sin approved_at, se omite");
                    $skipped++;

                    continue;
                }

                $this->line(" - {$order->folio} | status={$order->status} | issued_at={$order->approved_at->format('Y-m-d H:i:s')}");

                if (! $dryRun) {
                    // toBase(): no dispara eventos del modelo ni modifica updated_at.
                    DirectPurchaseOrder::query()
                        ->whereKey($order->id)
                        ->whereNull('issued_at')
                        ->toBase()
                        ->update(['issued_at' => $order->approved_at]);
                    $updated++;
                }
            }
        });

        $this->info($dryRun ? 'Dry-run completado.' : "OCD actualizadas: {$updated}");

        if ($skipped > 0) {
            $this->warn("OCD omitidas por no tener approved_at: {$skipped}");
        }

        return self::SUCCESS;
    }
}
