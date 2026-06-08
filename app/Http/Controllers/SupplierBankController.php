<?php

// app/Http/Controllers/SupplierBankController.php
namespace App\Http\Controllers;

use App\Http\Requests\BankDetailsRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierBankController extends Controller
{
    public function update(BankDetailsRequest $request, Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);

        // Solo toma las llaves que te interesan
        $data = $request->validated();

        // Normaliza vacío -> null
        foreach (['bank_name','bank_address','account_number','clabe','swift_bic','iban','aba_routing'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }

        // Moneda a MAYÚSCULAS si viene
        if (array_key_exists('currency', $data) && $data['currency'] !== null) {
            $data['currency'] = strtoupper($data['currency']);
        }

        // 🔐 Esto actualiza SOLO lo que viene en $data (no pisa lo demás)
        $supplier->update($data);

        // Asegura que respondes con valores recién guardados
        $supplier->refresh();

        return response()->json([
            'message'  => 'Datos bancarios guardados correctamente.',
            'supplier' => $supplier->only([
                'id','bank_name','bank_address','account_number','clabe',
                'currency','swift_bic','iban','aba_routing','us_bank_name'
            ]),
        ]);
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);

        $supplier->update([
            'bank_name'      => null,
            'bank_address'   => null,
            'account_number' => null,
            'clabe'          => null,
            'swift_bic'      => null,
            'iban'           => null,
            'aba_routing'    => null,
            // Mantengo currency; si quieres vaciarla también, ponla en null
        ]);

        return response()->json([
            'message' => 'Datos bancarios eliminados.',
        ]);
    }

    public function updateRepse(Request $request, \App\Models\Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);

        // Si no hay JS, puede llegar el fallback en CSV. Lo convertimos a JSON antes de validar.
        if ($request->filled('specialized_services_types_fallback') && !$request->filled('specialized_services_types')) {
            $csv = $request->input('specialized_services_types_fallback');
            $arr = array_values(array_filter(array_map('trim', explode(',', (string) $csv))));
            $request->merge(['specialized_services_types' => json_encode($arr)]);
        }

        $data = $request->validate([
            'provides_specialized_services' => ['required','boolean'],
            'repse_registration_number'     => ['nullable','string','max:100'],
            'repse_expiry_date'             => ['nullable','date'],
            'specialized_services_types'    => ['nullable','json'], // llega JSON desde el hidden
        ]);

        $supplier->update($data);

        return response()->json(['ok' => true]);
    }

    private function authorizeSupplier(Supplier $supplier): void
    {
        if (Auth::guard('supplier')->check()) {
            abort_unless((int) Auth::guard('supplier')->id() === (int) $supplier->id, 403);
        }
    }

}
