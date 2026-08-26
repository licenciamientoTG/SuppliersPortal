<?php

namespace App\Jobs;

use App\Services\SafeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSafeMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 1800];

    /** @param array<int, string> $cc */
    public function __construct(
        public string|array $to,
        public Mailable $mailable,
        public array $cc = [],
        public string $operation = 'del correo',
        public ?string $reference = null,
        public ?string $url = null,
    ) {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        Mail::to($this->to)->cc($this->cc)->send($this->mailable);
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
            [['exception' => $exception]],
        );
    }
}
