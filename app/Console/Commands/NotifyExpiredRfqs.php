<?php

namespace App\Console\Commands;

use App\Models\Rfq;
use App\Models\RfqExpiryNotification;
use App\Notifications\RfqExpiredNotification;
use App\Services\BuyerNotificationService;
use App\Services\SafeNotificationService;
use Illuminate\Console\Command;

class NotifyExpiredRfqs extends Command
{
    protected $signature = 'rfqs:notify-expired';

    protected $description = 'Notifica al requisitor y a Compras las RFQ vencidas.';

    public function handle(BuyerNotificationService $buyerNotifications, SafeNotificationService $safeNotifications): int
    {
        $notified = 0;

        Rfq::query()
            ->with(['requisition.requester'])
            ->whereNotNull('response_deadline')
            ->where('response_deadline', '<=', now())
            ->whereNotIn('status', ['DRAFT', 'COMPLETED', 'CANCELLED', 'REJECTED'])
            ->orderBy('id')
            ->each(function (Rfq $rfq) use ($buyerNotifications, $safeNotifications, &$notified): void {
                $recipients = $buyerNotifications->recipients([$rfq->requisition?->requester]);
                $pendingRecipients = $recipients->filter(function ($recipient) use ($rfq): bool {
                    return RfqExpiryNotification::firstOrCreate([
                        'rfq_id' => $rfq->id,
                        'user_id' => $recipient->id,
                    ])->wasRecentlyCreated;
                })->values();

                if ($pendingRecipients->isEmpty()) {
                    return;
                }

                $safeNotifications->notify(
                    new RfqExpiredNotification($rfq),
                    $pendingRecipients,
                    'de vencimiento de RFQ',
                    $rfq->folio,
                    route('rfq.show', $rfq),
                );

                $notified += $pendingRecipients->count();
            });

        $this->info("Destinatarios notificados por RFQ vencida: {$notified}.");

        return self::SUCCESS;
    }
}
