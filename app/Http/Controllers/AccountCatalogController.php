<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Subaccount;
use App\Services\AccountSubaccountSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountCatalogController extends Controller
{
    public function index(): View
    {
        $accounts = Account::query()
            ->with([
                'productServices:id,code,short_name,technical_description,product_type,status,is_active',
                'subaccounts' => fn ($query) => $query
                    ->with(['productServices:id,code,short_name,technical_description,product_type,status,is_active'])
                    ->withCount(['productServices', 'budgetProfiles', 'departments', 'users'])
                    ->orderBy('name'),
            ])
            ->withCount(['subaccounts', 'productServices'])
            ->orderBy('code')
            ->get();

        return view('account_catalog.index', compact('accounts'));
    }

    public function sync(): RedirectResponse
    {
        app(AccountSubaccountSyncService::class)->syncFromLegacy();

        return back()->with('success', 'Catálogo de cuentas y subcuentas sincronizado correctamente.');
    }

    public function updateAccount(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('accounts', 'code')->ignore($account->id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'is_fixed_asset' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account->forceFill([
            ...$data,
            'is_fixed_asset' => $request->boolean('is_fixed_asset'),
            'is_active' => $request->boolean('is_active'),
            'updated_by' => Auth::id(),
        ])->save();

        return back()->with('success', 'Cuenta actualizada correctamente.');
    }

    public function updateSubaccount(Request $request, Subaccount $subaccount): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('subaccounts', 'name')
                    ->where('account_id', $subaccount->account_id)
                    ->ignore($subaccount->id),
            ],
            'is_fixed_asset' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subaccount->forceFill([
            ...$data,
            'is_fixed_asset' => $request->boolean('is_fixed_asset'),
            'is_active' => $request->boolean('is_active'),
            'updated_by' => Auth::id(),
        ])->save();

        return back()->with('success', 'Subcuenta actualizada correctamente.');
    }
}
