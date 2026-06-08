<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierSiroc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SupplierSirocController extends Controller
{
    /**
     * Listar todos los SIROC de un proveedor
     */
    public function index(Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);
        $sirocs = $supplier->sirocs()->latest()->paginate(10);
        return view('sirocs.suppliers.index', compact('supplier', 'sirocs'));
    }

    /**
     * Listar todos los SIROC de un proveedor
     */
    public function adminIndex()
    {
        $sirocs = \App\Models\SupplierSiroc::with('supplier')
                    ->latest()
                    ->paginate(20);

        return view('sirocs.admin.index', compact('sirocs'));
    }

    /**
     * Formulario para crear un nuevo SIROC
     */
    public function create(Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);
        return view('sirocs.suppliers.create', compact('supplier'));
    }

    /**
     * Guardar un nuevo SIROC
     */
    public function store(Request $request, Supplier $supplier)
    {
        $this->authorizeSupplier($supplier);
        $validated = $request->validate([
            'siroc_number'    => 'required|string|max:50',
            'contract_number' => 'nullable|string|max:100',
            'work_name'       => 'nullable|string|max:255',
            'work_location'   => 'nullable|string|max:255',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'siroc_file'      => 'nullable|file|mimes:pdf|max:5120', // 5MB
            'status'          => 'required|in:vigente,suspendido,terminado',
            'observations'    => 'nullable|string',
        ]);

        if ($request->hasFile('siroc_file')) {
            $validated['siroc_file'] = $request->file('siroc_file')->store('sirocs', 'public');
        }

        $siroc = $supplier->sirocs()->create($validated);

        // arma payload “amigable” para el front
        $payload = $siroc->toArray();
        $payload['siroc_file_url'] = $siroc->siroc_file ? Storage::disk('public')->url($siroc->siroc_file) : null;
        $payload['show_url']    = route('suppliers.sirocs.show', [$supplier, $siroc]);
        $payload['edit_url']    = route('suppliers.sirocs.edit', [$supplier, $siroc]);
        $payload['destroy_url'] = route('suppliers.sirocs.destroy', [$supplier, $siroc]);

        return response()->json(['message' => 'Registro SIROC creado correctamente.', 'data' => $payload], 201);
    }

    /**
     * Ver detalle de un SIROC específico
     */
    public function show(Supplier $supplier, SupplierSiroc $siroc)
    {
        $this->authorizeSupplier($supplier);
        return view('sirocs.suppliers.show', compact('supplier', 'siroc'));
    }

    /**
     * Formulario para editar un SIROC
     */
    public function edit(Supplier $supplier, SupplierSiroc $siroc)
    {
        $this->authorizeSupplier($supplier);
        return view('sirocs.suppliers.edit', compact('supplier', 'siroc'));
    }

    /**
     * Actualizar un SIROC existente
     */
    public function update(Request $request, Supplier $supplier, SupplierSiroc $siroc)
    {
        $this->authorizeSupplier($supplier);
        // Asegura que el SIROC corresponda al supplier de la ruta
        if ($siroc->supplier_id != $supplier->id) {
            return response()->json([
                'message' => 'El registro SIROC no pertenece a este proveedor.'
            ], 404);
        }

        // Validación
        $validated = $request->validate([
            'siroc_number'    => 'required|string|max:50',
            'contract_number' => 'nullable|string|max:100',
            'work_name'       => 'nullable|string|max:255',
            'work_location'   => 'nullable|string|max:255',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'siroc_file'      => 'nullable|file|mimes:pdf|max:5120', // 5 MB
            'status'          => 'required|in:vigente,suspendido,terminado',
            'observations'    => 'nullable|string',
        ]);

        try {
            // Si viene un nuevo PDF, borra el anterior y guarda el nuevo
            if ($request->hasFile('siroc_file')) {
                if ($siroc->siroc_file && Storage::disk('public')->exists($siroc->siroc_file)) {
                    Storage::disk('public')->delete($siroc->siroc_file);
                }
                $validated['siroc_file'] = $request->file('siroc_file')->store('sirocs', 'public');
            }

            // Actualiza sólo los campos permitidos
            $siroc->update($validated);

            // Arma payload amigable para el front
            $payload = $siroc->toArray();
            $payload['siroc_file_url'] = $siroc->siroc_file
                ? Storage::disk('public')->url($siroc->siroc_file)
                : null;

            // (Opcional) URLs para acciones desde la tabla/modales
            $payload['show_url']    = route('suppliers.sirocs.show',    [$supplier, $siroc]);
            $payload['edit_url']    = route('suppliers.sirocs.edit',    [$supplier, $siroc]);
            $payload['destroy_url'] = route('suppliers.sirocs.destroy', [$supplier, $siroc]);

            return response()->json([
                'message' => 'Registro SIROC actualizado correctamente.',
                'data'    => $payload,
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo actualizar el SIROC. Inténtalo nuevamente.'
            ], 500);
        }
    }

    /**
     * Eliminar un SIROC
     */
    public function destroy(Request $request, Supplier $supplier, SupplierSiroc $siroc)
    {
        $this->authorizeSupplier($supplier);
        // Asegura pertenencia al supplier de la ruta
        if ($siroc->supplier_id != $supplier->id) {
            return response()->json([
                'message' => 'El registro SIROC no pertenece a este proveedor.'
            ], 404);
        }

        try {
            // Borra el PDF si existe
            if ($siroc->siroc_file && Storage::disk('public')->exists($siroc->siroc_file)) {
                Storage::disk('public')->delete($siroc->siroc_file);
            }

            $sirocId = $siroc->id;
            $siroc->forceDelete(); // si usas SoftDeletes y quieres borrar definitivo: $siroc->forceDelete();

            return response()->json([
                'message' => 'Registro SIROC eliminado correctamente.',
                'id'      => $sirocId,
            ], 200);

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'No se pudo eliminar el SIROC. Inténtalo nuevamente.'
            ], 500);
        }
    }

    private function authorizeSupplier(Supplier $supplier): void
    {
        if (Auth::guard('supplier')->check()) {
            abort_unless((int) Auth::guard('supplier')->id() === (int) $supplier->id, 403);
        }
    }
}
