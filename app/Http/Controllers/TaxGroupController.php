<?php

namespace App\Http\Controllers;

use App\Models\LedgerAccount;
use App\Models\TaxCode;
use App\Models\TaxGroup;
use App\Models\TaxGroupItem;
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
            'taxCodes' => TaxCode::query()->orderBy('name')->get(['id', 'one_goal_id', 'name', 'rate', 'calculation_type']),
        ]);
    }

    public function create(): View
    {
        return view('tax_groups.form', ['taxGroup' => new TaxGroup]);
    }

    public function store(Request $request): RedirectResponse
    {
        $taxGroup = TaxGroup::create([
            ...$this->groupAttributes($request),
            'one_goal_id' => (int) TaxGroup::max('one_goal_id') + 1,
        ]);

        return to_route('tax-groups.show', $taxGroup)->with('success', 'Grupo de impuestos creado. Agrega sus componentes y cuentas contables.');
    }

    public function edit(TaxGroup $taxGroup): View
    {
        return view('tax_groups.form', ['taxGroup' => $taxGroup]);
    }

    public function update(Request $request, TaxGroup $taxGroup): RedirectResponse
    {
        if ($request->has('items')) {
            $validated = $request->validate([
                'items' => ['required', 'array'],
                'items.*.ledger_account_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            ]);
            $itemIds = $taxGroup->items()->pluck('id')->all();

            foreach ($validated['items'] as $itemId => $attributes) {
                if (! in_array((int) $itemId, $itemIds, true)) {
                    abort(422, 'El componente no pertenece al grupo de impuestos indicado.');
                }

                $account = isset($attributes['ledger_account_id']) ? LedgerAccount::find($attributes['ledger_account_id']) : null;
                $taxGroup->items()->whereKey($itemId)->update([
                    'ledger_account_id' => $account?->id,
                    'one_goal_ledger_account_id' => $account?->one_goal_id ?? 0,
                ]);
            }
        }

        if ($request->has('name')) {
            $taxGroup->update($this->groupAttributes($request));
        }

        return to_route('tax-groups.show', $taxGroup)
            ->with('success', 'Configuración del grupo actualizada.');
    }

    public function addItem(Request $request, TaxGroup $taxGroup): RedirectResponse
    {
        $validated = $request->validate([
            'tax_code_id' => ['required', 'integer', 'exists:tax_codes,id'],
            'ledger_account_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
        ]);
        $taxCode = TaxCode::findOrFail($validated['tax_code_id']);
        $account = isset($validated['ledger_account_id']) ? LedgerAccount::find($validated['ledger_account_id']) : null;

        TaxGroupItem::create([
            'one_goal_id' => (int) TaxGroupItem::max('one_goal_id') + 1,
            'tax_group_id' => $taxGroup->id,
            'tax_code_id' => $taxCode->id,
            'ledger_account_id' => $account?->id,
            'one_goal_tax_code_id' => $taxCode->one_goal_id,
            'one_goal_ledger_account_id' => $account?->one_goal_id ?? 0,
            'is_active' => true,
        ]);

        return to_route('tax-groups.show', $taxGroup)->with('success', 'Componente agregado al grupo.');
    }

    public function deactivate(TaxGroup $taxGroup): RedirectResponse
    {
        $taxGroup->update(['is_active' => false]);

        return to_route('tax-groups.index')->with('success', 'Grupo desactivado; se conserva para historial.');
    }

    public function deactivateItem(TaxGroup $taxGroup, TaxGroupItem $taxGroupItem): RedirectResponse
    {
        abort_unless($taxGroupItem->tax_group_id === $taxGroup->id, 404);
        $taxGroupItem->update(['is_active' => false]);

        return to_route('tax-groups.show', $taxGroup)->with('success', 'Componente desactivado; se conserva para historial.');
    }

    /** @return array<string, mixed> */
    private function groupAttributes(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'one_goal_type_id' => ['required', 'integer', 'min:0'],
            'sat_tax_object' => ['nullable', 'string', 'size:2'],
            'is_payment_tax' => ['nullable', 'boolean'],
            'is_border_zone' => ['nullable', 'boolean'],
            'is_vat_tax' => ['nullable', 'boolean'],
            'is_south_border_zone' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            ...$validated,
            'one_goal_compound_id' => 0,
            'is_payment_tax' => $request->boolean('is_payment_tax'),
            'is_border_zone' => $request->boolean('is_border_zone'),
            'is_vat_tax' => $request->boolean('is_vat_tax'),
            'is_south_border_zone' => $request->boolean('is_south_border_zone'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
