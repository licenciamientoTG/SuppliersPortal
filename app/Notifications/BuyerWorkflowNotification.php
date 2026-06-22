<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuyerWorkflowNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $details
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $type,
        public string $subject,
        public string $heading,
        public string $intro,
        public array $details,
        public string $url,
        public string $buttonLabel,
        public string $message,
        public array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.notifications.buyer-workflow', [
                'name' => $notifiable->first_name ?? $notifiable->name,
                'heading' => $this->heading,
                'intro' => $this->intro,
                'details' => $this->details,
                'url' => $this->url,
                'buttonLabel' => $this->buttonLabel,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return array_merge($this->context, [
            'type' => $this->type,
            'url' => $this->url,
            'message' => $this->message,
        ]);
    }
}
