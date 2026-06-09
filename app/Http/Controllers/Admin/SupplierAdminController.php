<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierAdminController extends Controller
{
    public function index()
    {
        return view('admin.suppliers.index');
    }

    public function datatable(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) $request->input('search.value', ''));

        $query = Supplier::query()->select([
            'id',
            'company_name',
            'rfc',
            'contact_person',
            'contact_phone',
            'email',
            'bank_name',
            'last_login',
            'is_active',
            'approval_status',
            'document_status',
        ]);

        $recordsTotal = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $rows = $query
            ->orderBy('company_name')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows->map(function (Supplier $supplier) {
            $editUrl = route('admin.suppliers.edit', $supplier);
            $toggleUrl = route('admin.suppliers.toggle', $supplier);
            $deleteUrl = route('admin.suppliers.destroy', $supplier);
            $approveUrl = route('admin.suppliers.approve', $supplier);
            $rejectUrl = route('admin.suppliers.reject', $supplier);
            $docsUrl = route('admin.review.suppliers.show', $supplier);

            $approvalActions = match ($supplier->approval_status) {
                'pending' => <<<HTML
                    <button type="button" class="btn btn-sm btn-outline-success js-approve-supplier" data-url="{$approveUrl}" title="Aprobar"><i class="ti ti-circle-check"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-warning js-reject-supplier" data-url="{$rejectUrl}" title="Rechazar"><i class="ti ti-circle-x"></i></button>
                HTML,
                'rejected' => <<<HTML
                    <button type="button" class="btn btn-sm btn-outline-success js-approve-supplier" data-url="{$approveUrl}" title="Aprobar"><i class="ti ti-circle-check"></i></button>
                HTML,
                default => '',
            };

            $actions = <<<HTML
                <div class="d-flex justify-content-end gap-1">
                    <a href="{$editUrl}" class="btn btn-sm btn-outline-primary js-open-supplier-modal" data-url="{$editUrl}" title="Editar"><i class="ti ti-pencil"></i></a>
                    {$approvalActions}
                    <a href="{$docsUrl}" class="btn btn-sm btn-outline-info" target="_blank" title="Documentos"><i class="ti ti-file-description"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-supplier" data-url="{$toggleUrl}" title="Activar o desactivar"><i class="ti ti-switch-2"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger js-delete-supplier" data-url="{$deleteUrl}" data-name="{$supplier->company_name}" title="Eliminar"><i class="ti ti-trash"></i></button>
                </div>
            HTML;

            return [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'rfc' => $supplier->rfc,
                'contact_person' => $supplier->contact_person,
                'contact_phone' => $supplier->contact_phone,
                'email' => $supplier->email,
                'bank_name' => $supplier->bank_name,
                'last_login' => optional($supplier->last_login)->format('Y-m-d H:i'),
                'approval_status' => $this->badge($supplier->approval_status, [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                ]),
                'document_status' => $this->badge($supplier->document_status, [
                    'pending' => 'secondary',
                    'in_review' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                ]),
                'is_active' => (bool) $supplier->is_active,
                'actions' => $actions,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        $currencies = ['MXN' => 'MXN (Peso Mexicano)', 'USD' => 'USD (Dólar USA)'];

        return view('admin.suppliers.partials.form', compact('supplier', 'currencies'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('suppliers', 'email')->ignore($supplier->id), Rule::unique('users', 'email')],
            'is_active' => ['nullable', 'boolean'],
            'company_name' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13', Rule::unique('suppliers', 'rfc')->ignore($supplier->id)],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'supplier_type' => ['nullable', 'in:product,service,product_service,both'],
            'currency' => ['nullable', 'string', 'max:3'],
            'person_type' => ['nullable', 'in:fisica,moral,extranjero'],
            'tax_regimes' => ['nullable', 'array'],
            'economic_activity' => ['nullable', 'array'],
            'economic_activity.*' => ['nullable', 'string', 'max:150'],
            'default_payment_terms' => ['nullable', Rule::in(array_column(\App\Enum\PaymentTerm::cases(), 'value'))],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'clabe' => ['nullable', 'digits:18'],
            'us_bank_name' => ['nullable', 'string', 'max:100'],
            'swift_bic' => ['nullable', 'string', 'min:8', 'max:11'],
            'aba_routing' => ['nullable', 'digits:9'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bank_address' => ['nullable', 'string', 'max:255'],
            'provides_specialized_services' => ['nullable', 'boolean'],
            'repse_registration_number' => ['nullable', 'string'],
            'repse_expiry_date' => ['nullable', 'date'],
            'specialized_services_types' => ['nullable', 'array'],
            'specialized_services_types.*' => ['string'],
        ]);

        $validated['rfc'] = strtoupper((string) $validated['rfc']);
        $validated['postal_code'] = $request->filled('postal_code')
            ? preg_replace('/\D/', '', (string) $request->input('postal_code'))
            : null;
        $validated['tax_regimes'] = SupplierFiscalCatalog::normalizeSelectedRegimes(
            $request->input('person_type'),
            $request->input('tax_regimes', [])
        );
        $validated['economic_activity'] = SupplierFiscalCatalog::normalizeActivities(
            $request->input('economic_activity', [])
        );
        $validated['swift_bic'] = $request->filled('swift_bic') ? strtoupper((string) $request->input('swift_bic')) : null;
        $validated['iban'] = $request->filled('iban') ? strtoupper((string) $request->input('iban')) : null;
        $validated['is_active'] = (bool) $request->input('is_active', false);

        if (($validated['person_type'] ?? null) === 'extranjero') {
            $validated['tax_regimes'] = [];
        }

        if (! (bool) ($validated['provides_specialized_services'] ?? false)) {
            $validated['repse_registration_number'] = null;
            $validated['repse_expiry_date'] = null;
            $validated['specialized_services_types'] = [];
        }

        $supplier->update($validated);

        return response()->json(['message' => 'Proveedor actualizado correctamente.']);
    }

    public function toggle(Supplier $supplier)
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return response()->json([
            'message' => $supplier->is_active ? 'Proveedor activado correctamente.' : 'Proveedor desactivado correctamente.',
        ]);
    }

    public function approve(Request $request, Supplier $supplier)
    {
        if ($supplier->approval_status === 'approved') {
            return response()->json(['message' => 'El proveedor ya se encuentra aprobado.'], 422);
        }

        $supplier->update([
            'approval_status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'approval_notes' => $request->input('approval_notes'),
        ]);

        return response()->json(['message' => 'Proveedor aprobado correctamente.']);
    }

    public function reject(Request $request, Supplier $supplier)
    {
        if ($supplier->approval_status === 'approved') {
            return response()->json([
                'message' => 'Un proveedor aprobado no se rechaza desde esta acción directa. Revisa el flujo definido para cambios de estatus.',
            ], 422);
        }

        if ($supplier->approval_status === 'rejected') {
            return response()->json(['message' => 'El proveedor ya se encuentra rechazado.'], 422);
        }

        $request->validate([
            'approval_notes' => ['required', 'string', 'min:5'],
        ]);

        $supplier->update([
            'approval_status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'approval_notes' => $request->input('approval_notes'),
        ]);

        return response()->json(['message' => 'Proveedor rechazado correctamente.']);
    }

    public function destroy(Supplier $supplier)
    {
        DB::transaction(function () use ($supplier) {
            foreach ($supplier->documents as $document) {
                if ($document->path_file && Storage::disk('public')->exists($document->path_file)) {
                    Storage::disk('public')->delete($document->path_file);
                }
                $document->delete();
            }

            $supplier->delete();
        });

        return response()->json(['message' => 'Proveedor eliminado correctamente.']);
    }

    private function badge(string $status, array $palette): string
    {
        $color = $palette[$status] ?? 'secondary';
        $label = match ($status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'in_review' => 'En revisión',
            default => ucfirst($status),
        };

        return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
    }
}
