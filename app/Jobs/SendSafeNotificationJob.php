<?php

namespace App\Jobs;

use App\Services\SafeNotificationService;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSafeNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Evita ráfagas de autenticación SMTP cuando Gmail rechaza temporalmente la cuenta. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 1800];

    public function __construct(
        public Model $recipient,
        public Notification $notification,
        public string $operation,
        public ?string $reference = null,
        public ?string $url = null,
    ) {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        // Cancela también trabajos de correo a proveedores que ya estuvieran en cola.
        if ($this->recipient instanceof Supplier) {
            return;
        }

        // notifyNow evita que una Notification que ya implemente ShouldQueue se vuelva a encolar.
        $this->recipient->notifyNow($this->notification);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        app(SafeNotificationService::class)->reportDeliveryFailure(
            $this->operation,
            $this->reference,
            $this->url,
            [[
                'recipient_id' => $this->recipient->getKey(),
                'recipient_email' => $this->recipient->email ?? null,
                'exception' => $exception,
            ]],
        );
    }
}
