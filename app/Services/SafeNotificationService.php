<?php

namespace App\Services;

use App\Jobs\SendSafeNotificationJob;
use App\Models\User;
use App\Notifications\MailDeliveryFailedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafeNotificationService
{
    /**
     * Entrega una notificación sin permitir que una incidencia de correo
     * afecte la operación que la originó.
     *
     * @param  iterable<object>  $recipients
     */
    public function notify(
        Notification $notification,
        iterable $recipients,
        string $operation,
        ?string $reference = null,
        ?string $url = null,
    ): void {
        $failures = [];

        foreach ($recipients as $recipient) {
            if (! method_exists($recipient, 'notify')) {
                continue;
            }

            if ($recipient instanceof Model) {
                SendSafeNotificationJob::dispatch(
                    $recipient,
                    $notification,
                    $operation,
                    $reference,
                    $url,
                );

                continue;
            }

            try {
                $recipient->notify($notification);
            } catch (Throwable $exception) {
                $failures[] = [
                    'recipient_id' => $recipient->id ?? null,
                    'recipient_email' => $recipient->email ?? null,
                    'exception' => $exception,
                ];
            }
        }

        if ($failures !== []) {
            $this->reportDeliveryFailure($operation, $reference, $url, $failures);
        }
    }

    /** @param callable(): void $delivery */
    public function attempt(string $operation, callable $delivery, ?string $reference = null, ?string $url = null): bool
    {
        try {
            $delivery();

            return true;
        } catch (Throwable $exception) {
            $this->reportDeliveryFailure($operation, $reference, $url, [['exception' => $exception]]);

            return false;
        }
    }

    /** @param array<int, array<string, mixed>> $failures */
    public function reportDeliveryFailure(string $operation, ?string $reference, ?string $url, array $failures): void
    {
        Log::error('Mail notification delivery failed; the main operation was preserved.', [
            'operation' => $operation,
            'reference' => $reference,
            'failures' => $failures,
        ]);

        try {
            User::role('superadmin')->get()->each->notify(
                new MailDeliveryFailedNotification($operation, $reference, $url)
            );
        } catch (Throwable $exception) {
            Log::error('Unable to notify superadmins about a mail delivery failure.', [
                'operation' => $operation,
                'reference' => $reference,
                'exception' => $exception,
            ]);
        }
    }
}
