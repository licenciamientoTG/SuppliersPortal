<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $supplier = $request->user('supplier');

        abort_unless($supplier, 403);

        if (! $supplier->canAccessFullPortal()) {
            $message = match ($supplier->recalculateDocumentStatus()) {
                'rejected' => 'Tu acceso está limitado porque tienes documentos rechazados o vencidos.',
                'in_review' => 'Tu expediente contiene documentos pendientes de revisión por Compras.',
                'pending' => 'Completa los documentos obligatorios para continuar con tu alta.',
                'approved' => 'Tu expediente documental está completo; falta la aprobación final de Compras.',
                default => 'Tu cuenta de proveedor todavía no tiene acceso completo.',
            };

            return redirect()
                ->route('supplier.documents.index')
                ->with('error', $message);
        }

        return $next($request);
    }
}
