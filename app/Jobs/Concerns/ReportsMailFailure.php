<?php

namespace App\Jobs\Concerns;

use App\Services\SafeNotificationService;
use Throwable;

trait ReportsMailFailure
{
    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [300, 1800];
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        app(SafeNotificationService::class)->reportDeliveryFailure(
            $this->mailFailureOperation(),
            $this->mailFailureReference(),
            null,
            [['exception' => $exception]],
        );
    }

    protected function mailFailureOperation(): string
    {
        return 'de alerta de entrega';
    }

    abstract protected function mailFailureReference(): ?string;
}
