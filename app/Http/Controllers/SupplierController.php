<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\User;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function edit(User $user)
    {
        return view('users.staff.partials.supplier_form', compact('user'));
    }

    public function store(Request $request, User $user)
    {
        $data = $this->validateData($request);
        $data['user_id'] = $user->id;

        Supplier::create($data);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, User $user)
    {
        $supplier = $user->supplier;
        abort_unless($supplier, 404);

        $supplier->update($this->validateData($request));

        return response()->json(['ok' => true]);
    }

    protected function validateData(Request $request): array
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'supplier_type' => ['nullable', 'string', 'max:100'],
            'person_type' => ['nullable', 'in:fisica,moral,extranjero'],
            'tax_regimes' => ['nullable', 'array'],
            'tax_regimes.*' => ['string'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'clabe' => ['nullable', 'string', 'max:18'],
            'currency' => ['nullable', 'in:MXN,USD,EUR'],
            'swift_bic' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'bank_address' => ['nullable', 'string', 'max:255'],
            'aba_routing' => ['nullable', 'string', 'max:50'],
            'us_bank_name' => ['nullable', 'string', 'max:150'],
            'provides_specialized_services' => ['nullable', 'boolean'],
            'repse_registration_number' => ['nullable', 'string', 'max:100'],
            'repse_expiry_date' => ['nullable', 'date'],
            'specialized_services_types' => ['nullable', 'array'],
            'specialized_services_types.*' => ['string'],
            'economic_activity' => ['nullable', 'array'],
            'economic_activity.*' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['rfc'] = strtoupper((string) $validated['rfc']);
        if (array_key_exists('postal_code', $validated) && $validated['postal_code'] !== null) {
            $validated['postal_code'] = preg_replace('/\D/', '', (string) $validated['postal_code']);
        }
        $validated['tax_regimes'] = SupplierFiscalCatalog::normalizeSelectedRegimes(
            $validated['person_type'] ?? null,
            $validated['tax_regimes'] ?? []
        );
        $validated['economic_activity'] = SupplierFiscalCatalog::normalizeActivities(
            $validated['economic_activity'] ?? []
        );

        if (($validated['person_type'] ?? null) === 'extranjero') {
            $validated['tax_regimes'] = [];
        }

        return $validated;
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 20;

        $paginator = Supplier::query()
            ->notEfos69b()
            ->search($term)
            ->orderBy('company_name')
            ->simplePaginate($perPage, ['id', 'company_name', 'rfc'], 'page', $page);

        $results = collect($paginator->items())->map(function ($supplier) {
            $rfc = $supplier->rfc ? " ({$supplier->rfc})" : '';

            return [
                'id' => $supplier->id,
                'text' => Str::limit($supplier->company_name, 80) . $rfc,
            ];
        });

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => $paginator->hasMorePages()],
        ]);
    }
}
