<?php

namespace App\Http\Controllers;

use App\Models\SupplierDocumentType;
use App\Services\SupplierDocumentRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierDocumentTypeController extends Controller
{
    public function index(): View
    {
        return view('supplier_document_types.index', [
            'types' => SupplierDocumentType::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('supplier_document_types.form', ['type' => new SupplierDocumentType]);
    }

    public function store(Request $request, SupplierDocumentRequirementService $requirements): RedirectResponse
    {
        $type = SupplierDocumentType::create($this->validated($request));
        $requirements->synchronizeExistingForType($type);

        return redirect()->route('supplier-document-types.index')->with('success', 'Documento de catálogo creado.');
    }

    public function edit(SupplierDocumentType $supplierDocumentType): View
    {
        return view('supplier_document_types.form', ['type' => $supplierDocumentType]);
    }

    public function update(Request $request, SupplierDocumentType $supplierDocumentType, SupplierDocumentRequirementService $requirements): RedirectResponse
    {
        $supplierDocumentType->update($this->validated($request, $supplierDocumentType));
        $requirements->synchronizeExistingForType($supplierDocumentType->fresh());

        return redirect()->route('supplier-document-types.index')->with('success', 'Documento de catálogo actualizado.');
    }

    private function validated(Request $request, ?SupplierDocumentType $type = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('supplier_document_types', 'code')->ignore($type)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'renewal_mode' => ['required', Rule::in(SupplierDocumentType::RENEWAL_MODES)],
            'renewal_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'validity_source' => ['required', Rule::in(SupplierDocumentType::VALIDITY_SOURCES)],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_required'] = $request->boolean('is_required');
        $data['applies_to_physical'] = $request->boolean('applies_to_physical');
        $data['applies_to_legal'] = $request->boolean('applies_to_legal');
        $data['requires_repse'] = $request->boolean('requires_repse');
        if ($data['renewal_mode'] === 'periodic') {
            validator($data, ['renewal_interval_days' => ['required', 'integer', 'min:1']])->validate();
        } else {
            $data['renewal_interval_days'] = null;
        }
        if ($data['is_active'] && ! $type?->is_active) {
            $data['activated_at'] = now();
        }

        return $data;
    }
}
