<?php

namespace App\Policies;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\ReceivingLocation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Policy para autorización de operaciones en ReceivingLocation
 * 
 * Roles del sistema:
 * - superadmin: Acceso total a todo (manejado en before())
 * - buyer (Compras): Gestión completa de ubicaciones
 * - accounting (Contabilidad): Visualización y reportes
 * - authorizer (Autorizador): Visualización y reportes
 * - receiver (Receptor): Operaciones en su ubicación asignada
 * - supplier (Proveedor): Sin acceso a ubicaciones
 */
class ReceivingLocationPolicy
{
    use HandlesAuthorization;

    /**
     * Performance boost: Superadmin pasa todas las autorizaciones
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null; // Continuar con la verificación normal
    }

    /**
     * Determine if the user can view any models.
     * 
     * ¿Quién puede ver el listado de ubicaciones?
     * - buyer: siempre
     * - accounting: siempre
     * - authorizer: siempre
     * - receiver: NO
     * - supplier: NO
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Auth\Access\Response
     */
    public function viewAny(User $user): Response
    {
        $allowedRoles = ['buyer', 'accounting', 'authorizer'];

        if ($user->hasAnyRole($allowedRoles)) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para ver el listado de ubicaciones.');
    }

    /**
     * Determine if the user can view the model.
     * 
     * ¿Quién puede ver una ubicación específica?
     * - buyer: siempre
     * - accounting: siempre
     * - authorizer: siempre
     * - receiver: SOLO si es su ubicación asignada
     * - supplier: NO
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function view(User $user, ReceivingLocation $receivingLocation): Response
    {
        // Roles que pueden ver cualquier ubicación
        if ($user->hasAnyRole(['buyer', 'accounting', 'authorizer'])) {
            return Response::allow();
        }

        // Receiver solo puede ver su propia ubicación
        if ($user->hasRole('receiver')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para ver esta ubicación.');
    }

    /**
     * Determine if the user can create models.
     * 
     * ¿Quién puede crear nuevas ubicaciones?
     * - superadmin: siempre
     * - buyer: siempre (pueden necesitar crear nuevas ubicaciones)
     * - OTROS: NO
     * 
     * Las ubicaciones son datos maestros que no deben ser creados por cualquier usuario
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Auth\Access\Response
     */
    public function create(User $user): Response
    {
        if ($user->hasRole('superadmin')) {
            return Response::allow();
        }

        if ($user->hasRole('buyer')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para crear ubicaciones. Solo compras puede realizar esta acción.');
    }

    /**
     * Determine if the user can update the model.
     * 
     * ¿Quién puede actualizar una ubicación?
     * - superadmin: siempre
     * - buyer: siempre
     * - OTROS: NO
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function update(User $user, ReceivingLocation $receivingLocation): Response
    {
        if ($user->hasRole('buyer')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para modificar esta ubicación. Solo compras puede realizar esta acción.');
    }

    /**
     * Determine if the user can delete the model.
     * 
     * ¿Quién puede eliminar una ubicación?
     * - superadmin: siempre
     * - buyer: siempre (con restricciones)
     * - OTROS: NO
     * 
     * RESTRICCIONES DE NEGOCIO:
     * - No se puede eliminar una ubicación que tenga OCs pendientes
     * - No se puede eliminar una ubicación que tenga recepciones registradas
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function delete(User $user, ReceivingLocation $receivingLocation): Response
    {
        // Solo buyer puede eliminar
        if (!$user->hasRole('buyer')) {
            return Response::deny('No tienes permiso para eliminar ubicaciones.');
        }

        // Validar que no tenga órdenes de compra pendientes
        if ($receivingLocation->purchaseOrders()->whereIn('status', ['pending', 'partial'])->exists()) {
            return Response::deny('No se puede eliminar la ubicación porque tiene órdenes de compra pendientes de recibir.');
        }

        // Validar que no tenga recepciones registradas
        if ($receivingLocation->receptions()->exists()) {
            return Response::deny('No se puede eliminar la ubicación porque tiene recepciones registradas. Considere desactivarla en lugar de eliminarla.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can block the portal for this location.
     * 
     * ¿Quién puede bloquear el portal de proveedores? (FRU punto 4.5 - CA-028)
     * - superadmin: siempre
     * - buyer: siempre
     * - accounting: en casos críticos (por ejemplo, provisiones vencidas)
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function blockPortal(User $user, ReceivingLocation $receivingLocation): Response
    {
        if ($user->hasRole('buyer')) {
            return Response::allow();
        }

        if ($user->hasRole('accounting')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para bloquear el portal de proveedores.');
    }

    /**
     * Determine if the user can unblock the portal for this location.
     * 
     * ¿Quién puede desbloquear el portal de proveedores? (FRU punto 4.5 - CA-030)
     * - superadmin: siempre
     * - buyer: siempre
     * - accounting: siempre
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function unblockPortal(User $user, ReceivingLocation $receivingLocation): Response
    {
        if ($user->hasAnyRole(['buyer', 'accounting'])) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para desbloquear el portal de proveedores.');
    }

    /**
     * Roles que pueden usar recepción masiva en cualquier ubicación.
     */
    public const MASS_RECEPTION_ROLES = ['superadmin', 'buyer', 'authorizer', 'receiver'];

    /**
     * Determine if the user can use mass reception at this location.
     *
     * Cualquier usuario con rol superadmin, buyer, authorizer o receiver puede
     * recibir en cualquier ubicación.
     */
    public function useMassReception(User $user, ReceivingLocation $receivingLocation): Response
    {
        if ($user->hasAnyRole(self::MASS_RECEPTION_ROLES)) {
            return Response::allow();
        }

        return Response::deny($this->massReceptionDeniedMessage($user));
    }

    /**
     * Determina si el usuario puede registrar la recepción de una OC / OCD.
     *
     * Puede recibir quien tenga un rol de recepción, o el requisitor:
     * - OC: el solicitante de la requisición origen (requested_by).
     * - OCD: el creador de la orden (no tiene requisición).
     */
    public function receiveOrder(User $user, PurchaseOrder|DirectPurchaseOrder $order): Response
    {
        if ($user->hasAnyRole(self::MASS_RECEPTION_ROLES) || $this->isRequester($user, $order)) {
            return Response::allow();
        }

        return Response::deny($this->massReceptionDeniedMessage($user));
    }

    public function isRequester(User $user, PurchaseOrder|DirectPurchaseOrder $order): bool
    {
        if ($order instanceof DirectPurchaseOrder) {
            return (int) $order->created_by === $user->id;
        }

        return $order->requisition_id !== null
            && (int) $order->requisition?->requested_by === $user->id;
    }

    /**
     * Explica por qué el usuario no puede recibir y cómo habilitarlo.
     */
    public function massReceptionDeniedMessage(User $user): string
    {
        $label = fn (string $role): string => __("roles.{$role}") !== "roles.{$role}" ? __("roles.{$role}") : $role;

        $userRoles = $user->getRoleNames()->map($label)->implode(', ');
        $required = collect(self::MASS_RECEPTION_ROLES)->map($label)->implode(', ');

        $current = $userRoles !== ''
            ? "Tus roles actuales son: {$userRoles}."
            : 'Tu usuario no tiene ningún rol asignado.';

        return 'No puedes registrar esta recepción. Solo pueden recibir material los usuarios con alguno de estos roles: '
            ."{$required}; o la persona que solicitó la requisición (requisitor) de esta orden. {$current} "
            .'Para habilitarte, solicita a un administrador que te asigne el rol Recibidor.';
    }

    /**
     * Determine if the user can view pending receptions for this location.
     * 
     * ¿Quién puede ver recepciones pendientes de esta ubicación?
     * - superadmin: siempre
     * - buyer: siempre
     * - accounting: siempre
     * - authorizer: siempre
     * - receiver: SOLO su ubicación
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function viewPendingReceptions(User $user, ReceivingLocation $receivingLocation): Response
    {
        // Roles que pueden ver pendientes de cualquier ubicación
        if ($user->hasAnyRole(['buyer', 'accounting', 'authorizer'])) {
            return Response::allow();
        }

        // Receiver solo su ubicación
        if ($user->hasRole('receiver')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para ver las recepciones pendientes de esta ubicación.');
    }

    /**
     * Determine if the user can export reports for this location.
     * 
     * ¿Quién puede exportar reportes de esta ubicación?
     * - superadmin: siempre
     * - buyer: siempre
     * - accounting: siempre
     * - authorizer: siempre
     * - receiver: solo su ubicación (puede necesitar reportes operativos)
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function exportReports(User $user, ReceivingLocation $receivingLocation): Response
    {
        // Roles que pueden exportar de cualquier ubicación
        if ($user->hasAnyRole(['buyer', 'accounting', 'authorizer'])) {
            return Response::allow();
        }

        // Receiver puede exportar solo su ubicación
        if ($user->hasRole('receiver')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para exportar reportes de esta ubicación.');
    }

    /**
     * Determine if the user can view the location dashboard.
     * 
     * ¿Quién puede ver el dashboard de esta ubicación?
     *
     * @param \App\Models\User $user
     * @param \App\Models\ReceivingLocation $receivingLocation
     * @return \Illuminate\Auth\Access\Response
     */
    public function viewDashboard(User $user, ReceivingLocation $receivingLocation): Response
    {
        // Todos los roles activos pueden ver dashboards, pero filtrados
        if ($user->hasAnyRole(['buyer', 'accounting', 'authorizer'])) {
            return Response::allow();
        }

        if ($user->hasRole('receiver')) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para ver el dashboard de esta ubicación.');
    }
}
