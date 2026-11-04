<?php

namespace App\Notifications;

use App\Models\BudgetMovement;
use App\Models\BudgetMovementApprovalSetting;
use App\Models\BudgetMovementDecision;
use App\Models\BudgetMovementDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Aviso de cualquier paso del flujo de movimientos presupuestales. El contenido del correo
 * (asunto, paso activo, adjuntos) se deduce del estado de la solicitud al enviarlo.
 */
class BudgetMovementWorkflowNotification extends Notification
{
    use Queueable;

    /** Tope de adjuntos por correo; Gmail rechaza mensajes de más de 25 MB. */
    public const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    private const TYPE_LABELS = [
        BudgetMovement::TYPE_TRANSFER => 'Transferencia',
        BudgetMovement::TYPE_INCREASE => 'Ampliación',
        BudgetMovement::TYPE_DECREASE => 'Reducción',
    ];

    public function __construct(public readonly BudgetMovement $movement, public readonly string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $movement = $this->movement->fresh(['details.costCenter.responsible', 'details.expenseCategory', 'details.budgetCedula', 'creator', 'approver', 'decisions.actor', 'attachments']) ?? $this->movement;
        $data = $this->mailData($movement, $notifiable);

        $mail = (new MailMessage)
            ->subject($data['subject'])
            ->view('emails.notifications.budget-movement-workflow', $data);

        // La evidencia viaja a quien tiene que decidir (validar o autorizar).
        if ($data['isPending']) {
            foreach ($data['attached'] as $attachment) {
                $mail->attach(Storage::disk($attachment->disk)->path($attachment->file_path), [
                    'as' => $attachment->original_name,
                    'mime' => $attachment->mime_type,
                ]);
            }
        }

        return $mail;
    }

    /**
     * Arma las variables de la vista. Público para poder previsualizar el correo sin enviarlo.
     */
    public function mailData(BudgetMovement $movement, object $notifiable): array
    {
        $type = self::TYPE_LABELS[$movement->movement_type] ?? $movement->movement_type;
        $amount = '$'.number_format((float) $movement->total_amount, 2);
        $isTransfer = $movement->isTransfer();
        $settings = BudgetMovementApprovalSetting::query()->with(['director', 'substitute'])->first();
        $lastDecision = fn (string $action) => $movement->decisions->where('action', $action)->sortByDesc('created_at')->first();
        $originApproval = $movement->decisions->where('stage', BudgetMovementDecision::STAGE_ORIGIN)->where('action', BudgetMovementDecision::ACTION_APPROVED)->sortByDesc('created_at')->first();
        $originDetail = $movement->details->firstWhere('detail_type', BudgetMovementDetail::TYPE_ORIGIN);
        $isRecipient = fn ($user) => $user && (int) $user->id === (int) ($notifiable->id ?? 0);

        [$subject, $heading, $tone] = match ($movement->status) {
            BudgetMovement::STATUS_PENDING_ORIGIN => ["Te toca validar: {$type} #{$movement->id} · {$amount}", 'Te toca validar esta transferencia', 'info'],
            BudgetMovement::STATUS_PENDING_EXECUTIVE => ["Te toca autorizar: {$type} #{$movement->id} · {$amount}", 'Te toca autorizar este movimiento', 'info'],
            BudgetMovement::STATUS_RETURNED => ["Devuelta para corrección: {$type} #{$movement->id}", 'Tu solicitud fue devuelta para corrección', 'warning'],
            BudgetMovement::STATUS_APPROVED => ["Autorizada: {$type} #{$movement->id} · {$amount}", 'Tu solicitud fue autorizada y aplicada', 'success'],
            BudgetMovement::STATUS_REJECTED => ["Rechazada: {$type} #{$movement->id}", 'Tu solicitud fue rechazada', 'danger'],
            default => ["{$type} #{$movement->id}", "{$type} #{$movement->id}", 'info'],
        };

        // Ruta de aprobación: done | now | returned | failed | pending
        $status = $movement->status;
        $steps = [['label' => 'Enviada', 'state' => 'done', 'who' => $movement->creator?->name]];
        if ($isTransfer) {
            $originOwner = $originDetail?->costCenter?->responsible;
            $steps[] = match ($status) {
                BudgetMovement::STATUS_PENDING_ORIGIN => ['label' => 'Origen', 'state' => 'now', 'who' => $isRecipient($originOwner) ? 'Ahora: tú' : 'Ahora: '.($originOwner?->name ?? 'responsable')],
                BudgetMovement::STATUS_RETURNED => ['label' => 'Origen', 'state' => 'returned', 'who' => 'Devuelta'],
                default => ['label' => 'Origen', 'state' => 'done', 'who' => $originApproval?->actor?->name],
            };
        }
        $steps[] = match ($status) {
            BudgetMovement::STATUS_PENDING_EXECUTIVE => ['label' => 'Dirección', 'state' => 'now', 'who' => in_array((int) ($notifiable->id ?? 0), $settings?->executiveApproverIds() ?? [], true) ? 'Ahora: tú' : 'Ahora: '.($settings?->director?->name ?? 'Dirección')],
            BudgetMovement::STATUS_APPROVED => ['label' => 'Dirección', 'state' => 'done', 'who' => $movement->approver?->name],
            BudgetMovement::STATUS_REJECTED => ['label' => 'Dirección', 'state' => 'failed', 'who' => 'Rechazada'],
            default => ['label' => 'Dirección', 'state' => 'pending', 'who' => 'Pendiente'],
        };
        $steps[] = ['label' => 'Aplicada', 'state' => $status === BudgetMovement::STATUS_APPROVED ? 'done' : 'pending', 'who' => $status === BudgetMovement::STATUS_APPROVED ? $movement->approved_at?->format('d/m/Y') : 'Pendiente'];

        $lines = $movement->details->map(fn (BudgetMovementDetail $detail) => [
            'label' => match ($detail->detail_type) {
                BudgetMovementDetail::TYPE_ORIGIN => 'Sale de',
                BudgetMovementDetail::TYPE_DESTINATION => 'Llega a',
                default => $movement->movement_type === BudgetMovement::TYPE_DECREASE ? 'Se reduce en' : 'Se amplía en',
            },
            'center' => trim(($detail->costCenter?->code ?? '').' · '.($detail->costCenter?->name ?? ''), ' ·'),
            'account' => trim(($detail->expenseCategory?->name ?? '').' › '.($detail->budgetCedula?->name ?? ''), ' ›'),
            'month' => $detail->month_name.' '.$movement->fiscal_year,
            'amount' => ((float) $detail->amount < 0 ? '−' : '+').'$'.number_format(abs((float) $detail->amount), 2),
            'negative' => (float) $detail->amount < 0,
        ])->sortBy(fn ($line) => $line['label'] === 'Llega a' ? 1 : 0)->values()->all();

        $isPending = in_array($status, [BudgetMovement::STATUS_PENDING_ORIGIN, BudgetMovement::STATUS_PENDING_EXECUTIVE], true);

        // Otras personas que también pueden atender el paso actual.
        $alsoCan = null;
        if ($status === BudgetMovement::STATUS_PENDING_EXECUTIVE && $settings) {
            $others = [];
            if ($settings->director && ! $isRecipient($settings->director)) {
                $others[] = $settings->director->name.' (Director General)';
            }
            if ($settings->substitute && in_array((int) $settings->substitute->id, $settings->executiveApproverIds(), true) && ! $isRecipient($settings->substitute)) {
                $others[] = $settings->substitute->name.' (suplente, vigente hasta el '.$settings->substitute_ends_at?->format('d/m/Y').')';
            }
            $alsoCan = $others ? 'También puede atenderla: '.implode(' o ', $others).'.' : null;
        } elseif ($status === BudgetMovement::STATUS_PENDING_ORIGIN) {
            $alsoCan = 'También puede validarla cualquier Superadministrador.';
        }

        $reasonDecision = match ($status) {
            BudgetMovement::STATUS_RETURNED => $lastDecision(BudgetMovementDecision::ACTION_RETURNED),
            BudgetMovement::STATUS_REJECTED => $lastDecision(BudgetMovementDecision::ACTION_REJECTED),
            default => null,
        };

        // Adjuntos que caben en el correo, del más reciente al más antiguo.
        $attached = collect();
        $left = collect();
        $budget = self::MAX_ATTACHMENT_BYTES;
        foreach ($movement->attachments->sortByDesc('created_at') as $attachment) {
            $size = (int) $attachment->file_size;
            if ($isPending && $size <= $budget && Storage::disk($attachment->disk)->exists($attachment->file_path)) {
                $attached->push($attachment);
                $budget -= $size;
            } else {
                $left->push($attachment);
            }
        }

        return [
            'name' => $notifiable->name ?? null,
            'subject' => $subject,
            'heading' => $heading,
            'tone' => $tone,
            'intro' => $this->message,
            'steps' => $steps,
            'lines' => $lines,
            'isTransfer' => $isTransfer,
            'folio' => "{$type} #{$movement->id}",
            'amount' => $amount,
            'justification' => $movement->justification,
            'creatorName' => $movement->creator?->name,
            'reason' => $reasonDecision ? ['text' => $reasonDecision->comments, 'by' => $reasonDecision->actor?->name, 'at' => $reasonDecision->created_at?->format('d/m/Y H:i')] : null,
            'alsoCan' => $alsoCan,
            'isPending' => $isPending,
            'attached' => $attached,
            'notAttached' => $isPending ? $left : collect(),
            'url' => route('budget_movements.show', $movement),
            'buttonLabel' => match ($status) {
                BudgetMovement::STATUS_PENDING_ORIGIN => 'Revisar y validar',
                BudgetMovement::STATUS_PENDING_EXECUTIVE => 'Revisar y autorizar',
                BudgetMovement::STATUS_RETURNED => 'Corregir solicitud',
                default => 'Ver solicitud',
            },
        ];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'budget_movement_workflow', 'budget_movement_id' => $this->movement->id, 'url' => route('budget_movements.show', $this->movement), 'message' => $this->message];
    }
}
