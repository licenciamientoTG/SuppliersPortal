<?php

namespace App\Http\Controllers;

use App\Enum\RequisitionStatus;
use App\Mail\RequisitionFeedbackMail;
use App\Models\Requisition;
use App\Notifications\BuyerWorkflowNotification;
use App\Notifications\NewRequisitionForPurchasingNotification;
use App\Notifications\RequisitionFeedbackNotification;
use App\Notifications\RequisitionInQuotationNotification;
use App\Notifications\RequisitionRejectedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use App\Services\BuyerNotificationService;
use App\Services\QuotationRejectionWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RequisitionWorkflowController extends Controller
{
    public function __construct(
        private QuotationRejectionWorkflowService $quotationRejectionWorkflowService,
        private BuyerNotificationService $buyerNotificationService,
    ) {}

    public function validationInbox(Request $request)
    {
        $rows = Requisition::with([
            'company:id,name',
            'costCenter:id,code,name',
            'department:id,name',
            'requester:id,name',
        ])
            ->where('status', RequisitionStatus::PENDING->value)
            ->orderByDesc('id')
            ->paginate(20);

        return view('requisitions.inbox.validation', compact('rows'));
    }

    public function showValidationPage(Requisition $requisition)
    {
        if ($requisition->status !== RequisitionStatus::PENDING) {
            return redirect()
                ->route('requisitions.inbox.validation')
                ->with('error', 'Solo se pueden validar requisiciones en estado PENDIENTE.');
        }

        $requisition->load([
            'requester',
            'company',
            'department',
            'items.costCenter',
            'items.productService',
            'items.expenseCategory',
            'feedbacks.buyer',
        ]);

        return view('requisitions.validate', compact('requisition'));
    }

    public function hold(Request $request, Requisition $requisition)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! in_array($requisition->status, [RequisitionStatus::PENDING->value, RequisitionStatus::PAUSED->value], true)) {
            return $this->respond($request, false, 'La requisición no puede ser pausada en su estado actual.');
        }

        $requisition->update([
            'status' => RequisitionStatus::PAUSED->value,
            'pause_reason' => $data['reason'],
            'paused_by' => Auth::id(),
            'paused_at' => now(),
        ]);

        return $this->respond($request, true, '⏸️ Requisición puesta en espera.');
    }

    public function resume(Request $request, Requisition $requisition)
    {
        if ($requisition->status !== RequisitionStatus::PAUSED->value) {
            return $this->respond($request, false, 'La requisición no está pausada.');
        }

        $requisition->update([
            'status' => RequisitionStatus::PENDING->value,
            'pause_reason' => null,
            'paused_by' => null,
            'paused_at' => null,
            'reactivated_by' => Auth::id(),
            'reactivated_at' => now(),
        ]);

        return $this->respond($request, true, '▶️ Requisición reanudada.');
    }

    public function approveForQuotation(Request $request, Requisition $requisition)
    {
        if ($requisition->status === RequisitionStatus::IN_QUOTATION) {
            return $this->respond($request, true, 'Esta requisición ya está en proceso de cotización.');
        }

        if ($requisition->status !== RequisitionStatus::PENDING) {
            return $this->respond($request, false, 'Solo se pueden validar requisiciones en estado Pendiente.');
        }

        $requisition->update([
            'status' => RequisitionStatus::IN_QUOTATION->value,
            'updated_by' => Auth::id(),
            'pause_reason' => null,
            'paused_by' => null,
            'paused_at' => null,
        ]);

        if ($requisition->requester) {
            $requisition->requester->notify(new RequisitionInQuotationNotification($requisition));
        }

        $requisition->loadMissing(['requester', 'company']);

        $this->buyerNotificationService->notify(
            new BuyerWorkflowNotification(
                type: 'buyer_requisition_in_quotation',
                subject: 'Requisición lista para cotización - '.$requisition->folio,
                heading: 'Requisición en cotización',
                intro: 'la requisición fue validada y ya puede avanzar en el proceso de cotización.',
                details: [
                    'Requisición' => $requisition->folio,
                    'Solicitante' => $requisition->requester?->name ?? 'N/A',
                    'Empresa' => $requisition->company?->name ?? 'N/A',
                ],
                url: route('requisitions.show', $requisition),
                buttonLabel: 'Ver requisición',
                message: 'La requisición '.$requisition->folio.' fue validada y pasó a cotización.',
                context: [
                    'requisition_id' => $requisition->id,
                    'requisition_folio' => $requisition->folio,
                ],
            ),
        );

        return $this->respond($request, true, '✅ Requisición validada. Puede proceder con el proceso de cotización.');
    }

    public function feedback(Request $request, Requisition $requisition)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $buyer = Auth::user();

        if (! $requisition->requester?->email) {
            return $this->respond($request, false, 'La requisicion no tiene un requisitor con correo valido.');
        }

        try {
            $feedback = DB::transaction(function () use ($requisition, $buyer, $data) {
                return $requisition->feedbacks()->create([
                    'buyer_user_id' => $buyer->id,
                    'message' => $data['message'],
                    'sent_at' => now(),
                ]);
            });

            $feedback->load('buyer');

            Mail::to($requisition->requester->email)
                ->cc($buyer->email)
                ->send(new RequisitionFeedbackMail(
                    $requisition,
                    $feedback,
                    $buyer,
                    route('requisitions.show', $requisition->id)
                ));

            $requisition->requester->notify(new RequisitionFeedbackNotification($requisition, $feedback));

            return $this->respond($request, true, 'Retroalimentacion enviada al requisitor correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error al enviar retroalimentacion de requisicion', [
                'requisition_id' => $requisition->id,
                'buyer_id' => $buyer?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->respond($request, false, 'No se pudo enviar la retroalimentacion.');
        }
    }

    public function reject(Request $request, Requisition $requisition)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if (! $requisition->canBeRejected()) {
            return $this->respond(
                $request,
                false,
                "Operación denegada. El estado actual ({$requisition->status->label()}) no permite el rechazo."
            );
        }

        try {
            $success = $requisition->reject(
                $data['rejection_reason'],
                Auth::id()
            );

            if (! $success) {
                throw new \RuntimeException('El modelo Requisition se negó a procesar el rechazo.');
            }

            activity()
                ->performedOn($requisition)
                ->causedBy(Auth::user())
                ->withProperty('motivo', $data['rejection_reason'])
                ->log('Requisición rechazada por el departamento de compras');

            if ($requisition->requester) {
                $requisition->requester->notify(new RequisitionRejectedNotification($requisition));
            }

            return $this->respond($request, true, '❌ Requisición rechazada. El solicitante ha sido notificado por correo y sistema.');
        } catch (\Throwable $e) {
            Log::error("Falla crítica en rechazo de requisición ID {$requisition->id}: ".$e->getMessage());

            return $this->respond(
                $request,
                false,
                'Error interno al procesar el rechazo. El equipo ya fue informado.'
            );
        }
    }

    public function cancel(Request $request, Requisition $requisition)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $isRequester = $requisition->requested_by === $user->id;

        if ($requisition->status === RequisitionStatus::APPROVED->value) {
            if (! $user->hasRole(['superadmin', 'admin'])) {
                return $this->respond($request, false, 'Solo administradores pueden cancelar una requisición aprobada.');
            }
        } elseif ($requisition->status === RequisitionStatus::DRAFT->value) {
            if (! $isRequester && ! $user->hasRole(['superadmin', 'admin'])) {
                return $this->respond($request, false, 'Solo el requisitor puede cancelar un borrador.');
            }
        }

        if (! $requisition->canBeCancelled()) {
            return $this->respond($request, false, 'Esta requisición no puede ser cancelada en su estado actual.');
        }

        $this->quotationRejectionWorkflowService->cancelRequisitionFromPurchasing(
            $requisition,
            Auth::id(),
            $data['reason']
        );

        return $this->respond($request, true, '🛑 Requisición cancelada.');
    }

    public function submitToApproval(Request $request, Requisition $requisition)
    {
        if (! $requisition->canBeSubmitted() && ! $requisition->isPaused()) {
            return $this->respond($request, false, 'La requisición no puede ser enviada desde su estado actual.');
        }

        if ($requisition->items()->count() === 0) {
            return $this->respond($request, false, 'La requisición debe tener al menos una partida (RN-003).');
        }

        try {
            DB::beginTransaction();

            $requisition->update([
                'status' => RequisitionStatus::PENDING,
                'pause_reason' => null,
                'paused_by' => null,
                'paused_at' => null,
                'updated_by' => Auth::id(),
            ]);

            $requisition->requester?->notify(new RequisitionSubmittedNotification($requisition));
            $this->buyerNotificationService->notify(
                new NewRequisitionForPurchasingNotification($requisition->fresh(['requester', 'company', 'department']))
            );

            DB::commit();

            return $this->respond($request, true, '📤 Requisición enviada a Compras.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error al enviar requisición a Compras', [
                'requisition_id' => $requisition->id,
                'error' => $e->getMessage(),
            ]);

            return $this->respond($request, false, 'Error al enviar la requisición: '.$e->getMessage());
        }
    }

    private function respond(Request $request, bool $ok, string $msg)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $ok,
                'message' => $msg,
            ], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $msg);
    }
}
