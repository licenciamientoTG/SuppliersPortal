<?php

namespace App\Http\Controllers;

use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\Subaccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetProfileController extends Controller
{
    public function index(): View
    {
        $subaccounts = Subaccount::query()
            ->with('account:id,code,name')
            ->active()
            ->orderBy('account_id')
            ->orderBy('name')
            ->get();

        $profiles = BudgetProfile::query()
            ->with(['department:id,name', 'subaccounts.account', 'users:id'])
            ->withCount(['subaccounts', 'users'])
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->with(['budgetProfiles' => fn ($query) => $query->withCount(['subaccounts', 'users'])->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('budget_profiles.index', compact(
            'subaccounts',
            'profiles',
            'departments'
        ));
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $this->validateProfile($request);

        $profile = BudgetProfile::create($data);
        $profile->subaccounts()->sync($request->input('subaccount_ids', []));

        return back()->with('success', 'Perfil presupuestal creado correctamente.');
    }

    public function updateProfile(Request $request, BudgetProfile $budgetProfile): RedirectResponse
    {
        $data = $this->validateProfile($request, $budgetProfile);

        $budgetProfile->update($data);
        $budgetProfile->subaccounts()->sync($request->input('subaccount_ids', []));
        $invalidUserIds = $budgetProfile->users()
            ->where('users.department_id', '!=', $budgetProfile->department_id)
            ->pluck('users.id');

        if ($invalidUserIds->isNotEmpty()) {
            $budgetProfile->users()->detach($invalidUserIds);
        }

        return back()->with('success', 'Perfil presupuestal actualizado correctamente.');
    }

    private function validateProfile(Request $request, ?BudgetProfile $profile = null): array
    {
        return $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('budget_profiles', 'key')->ignore($profile?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'subaccount_ids' => ['array'],
            'subaccount_ids.*' => ['integer', 'exists:subaccounts,id'],
        ]) + ['is_active' => false];
    }
}
