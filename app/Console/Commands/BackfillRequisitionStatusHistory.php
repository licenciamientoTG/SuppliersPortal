<?php

namespace App\Console\Commands;

use App\Models\Requisition;
use App\Models\RequisitionStatusHistory;
use Illuminate\Console\Command;

class BackfillRequisitionStatusHistory extends Command
{
    protected $signature = 'reports:backfill-requisition-history';
    protected $description = 'Reconstruye eventos de requisiciones a partir de fechas existentes.';

    public function handle(): int
    {
        Requisition::with(['rfqs', 'quotationSummaries', 'purchaseOrders.receptions'])->chunkById(100, function ($rows) {
            foreach ($rows as $requisition) {
                $events = [
                    ['CREATED', null, 'DRAFT', $requisition->created_at, $requisition->created_by ?? $requisition->requested_by],
                    ['VALIDATED', 'PENDING', 'IN_QUOTATION', $requisition->validated_at, $requisition->validated_by],
                    ['RFQ_SENT', null, null, $requisition->rfqs->whereNotNull('sent_at')->min('sent_at'), null],
                    ['QUOTATION_APPROVED', 'QUOTED', 'APPROVED', $requisition->quotationSummaries->whereNotNull('approved_at')->min('approved_at'), null],
                    ['ORDER_ISSUED', 'APPROVED', 'COMPLETED', $requisition->purchaseOrders->whereNotNull('issued_at')->min('issued_at'), null],
                    ['SUPPLIER_DELIVERED', null, null, $requisition->purchaseOrders->whereNotNull('supplier_delivered_at')->min('supplier_delivered_at'), null],
                    ['RECEIVED', null, null, $requisition->purchaseOrders->flatMap->receptions->whereNotNull('received_at')->min('received_at'), null],
                ];
                foreach ($events as [$type, $from, $to, $date, $user]) {
                    if (! $date) continue;
                    RequisitionStatusHistory::firstOrCreate(['requisition_id' => $requisition->id, 'event_type' => $type, 'occurred_at' => $date], ['from_status' => $from, 'to_status' => $to, 'user_id' => $user]);
                }
            }
        });
        $this->info('Historial reconstruido.'); return self::SUCCESS;
    }
}
