<?php

namespace App\Http\Controllers;

use App\Models\LedgerAccount;
use App\Models\TaxGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TaxGroupController extends Controller
{
    public function index(): View
    {
        $groups = TaxGroup::query();

        return view('tax_groups.index', [
            'totalGroups' => (clone $groups)->count(),
            'activeGroups' => (clone $groups)->where('is_active', true)->count(),
            'groupsWithAccounts' => (clone $groups)->whereHas('items.ledgerAccount')->count(),
        ]);
    }

    public function datatable(): JsonResponse
    {
        return DataTables::of(TaxGroup::query()->withCount('items'))
            ->addColumn('components', function (TaxGroup $taxGroup): string {
                return $taxGroup->items_count > 0
                    ? '<span class="badge bg-primary">'.$taxGroup->items_count.' componentes</span>'
                    : '<span class="text-muted">Sin componentes</span>';
            })
            ->addColumn('status', fn (TaxGroup $taxGroup) => $taxGroup->is_active
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>')
            ->addColumn('actions', fn (TaxGroup $taxGroup) => '<a class="btn btn-outline-primary btn-sm" href="'.route('tax-groups.show', $taxGroup).'">Ver configuración</a>')
            ->rawColumns(['components', 'status', 'actions'])
            ->make(true);
    }

    public function show(TaxGroup $taxGroup): View
    {
        $taxGroup->load(['items.taxCode', 'items.ledgerAccount']);

        return view('tax_groups.show', [
            'taxGroup' => $taxGroup,
            'ledgerAccounts' => LedgerAccount::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'is_active', 'is_selectable']),
        ]);
    }

    public function update(Request $request, TaxGroup $taxGroup): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.ledger_account_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
        ]);

        $itemIds = $taxGroup->items()->pluck('id')->all();

        foreach ($validated['items'] as $itemId => $attributes) {
            if (! in_array((int) $itemId, $itemIds, true)) {
                abort(422, 'El componente no pertenece al grupo de impuestos indicado.');
            }

            $taxGroup->items()->whereKey($itemId)->update([
                'ledger_account_id' => $attributes['ledger_account_id'] ?? null,
            ]);
        }

        return to_route('tax-groups.show', $taxGroup)
            ->with('success', 'Cuentas contables actualizadas sin modificar impuestos ni otros catálogos.');
    }
}
