<?php

namespace App\Http\Controllers;

use App\Models\LedgerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class LedgerAccountController extends Controller
{
    public function index(): View
    {
        $accounts = LedgerAccount::query();

        return view('ledger_accounts.index', [
            'totalAccounts' => (clone $accounts)->count(),
            'activeAccounts' => (clone $accounts)->where('is_active', true)->count(),
            'linkedAccounts' => (clone $accounts)->whereHas('taxGroupItems')->count(),
        ]);
    }

    public function datatable(): JsonResponse
    {
        return DataTables::of(LedgerAccount::query()->with('parent:id,code,name')->withCount('taxGroupItems'))
            ->addColumn('parent', fn (LedgerAccount $account) => $account->parent?->display_label ?? '—')
            ->addColumn('usage', fn (LedgerAccount $account) => $account->tax_group_items_count > 0
                ? '<span class="badge bg-primary">'.$account->tax_group_items_count.' grupo(s)</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('status', fn (LedgerAccount $account) => $account->is_active
                ? '<span class="badge bg-success">Activa</span>'
                : '<span class="badge bg-secondary">Inactiva</span>')
            ->addColumn('actions', fn (LedgerAccount $account) => '<a class="btn btn-outline-primary btn-sm" href="'.route('ledger-accounts.edit', $account).'">Editar</a>')
            ->rawColumns(['usage', 'status', 'actions'])
            ->make(true);
    }

    public function create(): View
    {
        return view('ledger_accounts.form', [
            'ledgerAccount' => new LedgerAccount,
            'parents' => $this->parents(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LedgerAccount::create($this->validated($request));

        return to_route('ledger-accounts.index')->with('success', 'Cuenta contable creada.');
    }

    public function edit(LedgerAccount $ledgerAccount): View
    {
        return view('ledger_accounts.form', [
            'ledgerAccount' => $ledgerAccount,
            'parents' => $this->parents($ledgerAccount),
        ]);
    }

    public function update(Request $request, LedgerAccount $ledgerAccount): RedirectResponse
    {
        $ledgerAccount->update($this->validated($request, $ledgerAccount));

        return to_route('ledger-accounts.index')->with('success', 'Cuenta contable actualizada.');
    }

    public function deactivate(LedgerAccount $ledgerAccount): RedirectResponse
    {
        $ledgerAccount->update(['is_active' => false, 'is_selectable' => false]);

        return to_route('ledger-accounts.index')->with('success', 'Cuenta contable desactivada; se conserva para historial.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?LedgerAccount $ledgerAccount = null): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'alternate_name' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            'nature' => ['required', 'integer', 'between:0,9'],
            'account_level' => ['required', 'integer', 'between:0,9'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $parent = isset($validated['parent_id']) ? LedgerAccount::find($validated['parent_id']) : null;
        abort_if($ledgerAccount && $parent?->is($ledgerAccount), 422, 'Una cuenta no puede ser su propia cuenta padre.');

        return [
            ...$validated,
            'one_goal_id' => $ledgerAccount?->one_goal_id ?? ((int) LedgerAccount::max('one_goal_id') + 1),
            'one_goal_parent_id' => $parent?->one_goal_id ?? 0,
            'one_goal_type_id' => $ledgerAccount?->one_goal_type_id ?? 0,
            'one_goal_external_system_id' => $ledgerAccount?->one_goal_external_system_id,
            'is_active' => $request->boolean('is_active', true),
            'is_selectable' => $request->boolean('is_active', true),
        ];
    }

    private function parents(?LedgerAccount $ledgerAccount = null)
    {
        return LedgerAccount::query()
            ->when($ledgerAccount, fn ($query) => $query->whereKeyNot($ledgerAccount->id))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
