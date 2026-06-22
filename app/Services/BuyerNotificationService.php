<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class BuyerNotificationService
{
    /**
     * @param  iterable<mixed>  $additionalRecipients
     * @return Collection<int, User>
     */
    public function recipients(iterable $additionalRecipients = []): Collection
    {
        $buyers = User::role('buyer')->get();

        return $buyers
            ->concat(
                collect($additionalRecipients)
                    ->filter(fn ($recipient) => $recipient instanceof User)
            )
            ->unique('id')
            ->values();
    }

    /**
     * @param  iterable<mixed>  $additionalRecipients
     */
    public function notify(Notification $notification, iterable $additionalRecipients = []): void
    {
        $this->recipients($additionalRecipients)
            ->each
            ->notify($notification);
    }
}
