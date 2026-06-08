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
            return redirect()
                ->route('supplier.documents.index')
                ->with('error', 'Tu cuenta de proveedor sigue pendiente de aprobación por Compras.');
        }

        return $next($request);
    }
}
