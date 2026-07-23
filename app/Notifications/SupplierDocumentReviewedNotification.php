<?php

namespace App\Notifications;

use App\Models\SupplierDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierDocumentReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SupplierDocument $document,
        private readonly bool $accepted,
        private readonly bool $automatic = false,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->document->loadMissing('documentType', 'requirement');
        $name = $this->document->documentType?->name ?? $this->document->doc_type;
        $expiresAt = $this->document->requirement?->expires_at?->toDateString();

        $mail = (new MailMessage)
            ->subject($this->accepted ? 'Documento aprobado' : 'Documento rechazado')
            ->greeting('Hola, '.$notifiable->name)
            ->line(
                $this->accepted
                    ? "Tu documento {$name} fue aprobado".($this->automatic ? ' mediante validación automática.' : '.')
                    : "Tu documento {$name} fue rechazado."
            );

        if ($this->accepted && $expiresAt) {
            $mail->line("Vigente hasta: {$expiresAt}.");
        }

        if (! $this->accepted) {
            $mail->line('Motivo: '.($this->document->rejection_reason ?: 'Documento no aceptado.'));
            $mail->line('Carga una nueva versión que atienda las observaciones indicadas.');
        }

        return $mail
            ->action($this->accepted ? 'Ver mis documentos' : 'Cargar nueva versión', route('supplier.documents.index'))
            ->line('Este mensaje también está disponible en el centro de notificaciones del Portal de Proveedores.');
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->document->documentType?->name ?? $this->document->doc_type;

        return [
            'type' => $this->accepted ? 'supplier_document_accepted' : 'supplier_document_rejected',
            'supplier_document_id' => $this->document->id,
            'document_type' => $name,
            'automatic' => $this->automatic,
            'rejection_reason' => $this->accepted ? null : $this->document->rejection_reason,
            'url' => route('supplier.documents.index'),
            'message' => $this->accepted
                ? "Tu documento {$name} fue aprobado."
                : "Tu documento {$name} fue rechazado: ".($this->document->rejection_reason ?: 'Documento no aceptado.'),
        ];
    }
}
