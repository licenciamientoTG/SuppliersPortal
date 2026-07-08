<?php

namespace App\Http\Controllers;

use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\EmployeePositionBudgetProfile;
use App\Models\Subaccount;
use App\Models\User;
use App\Services\BudgetProfileHomologationService;
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
            ->with(['subaccounts.account'])
            ->withCount(['subaccounts', 'users', 'employeePositions'])
            ->orderBy('name')
            ->get();

        $positions = EmployeePositionBudgetProfile::query()
            ->with('budgetProfile:id,key,name')
            ->orderByDesc('employees_count')
            ->orderBy('raw_job_title')
            ->get();

        $departments = Department::query()
            ->with('subaccounts:id')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->with(['department:id,name', 'budgetProfile:id,name', 'subaccounts:id', 'employee:id,user_id,department,job_title'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id', 'budget_profile_id', 'job_title', 'is_active']);

        return view('budget_profiles.index', compact(
            'subaccounts',
            'profiles',
            'positions',
            'departments',
            'users'
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

        return back()->with('success', 'Perfil presupuestal actualizado correctamente.');
    }

    public function updatePosition(Request $request, EmployeePositionBudgetProfile $position): RedirectResponse
    {
        $data = $request->validate([
            'budget_profile_id' => ['nullable', 'exists:budget_profiles,id'],
            'is_excluded' => ['nullable', 'boolean'],
        ]);

        $isExcluded = (bool) ($data['is_excluded'] ?? false);

        $position->update([
            'budget_profile_id' => $isExcluded ? null : ($data['budget_profile_id'] ?? null),
            'is_excluded' => $isExcluded,
        ]);

        return back()->with('success', 'Homologacion de puesto actualizada correctamente.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'subaccount_ids' => ['array'],
            'subaccount_ids.*' => ['integer', 'exists:subaccounts,id'],
        ]);

        $department->subaccounts()->sync($data['subaccount_ids'] ?? []);

        return back()->with('success', 'Subcuentas del departamento actualizadas correctamente.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'budget_profile_id' => ['nullable', 'exists:budget_profiles,id'],
            'subaccount_ids' => ['array'],
            'subaccount_ids.*' => ['integer', 'exists:subaccounts,id'],
        ]);

        $user->forceFill([
            'department_id' => $data['department_id'] ?? null,
            'budget_profile_id' => $data['budget_profile_id'] ?? null,
        ])->save();
        $user->subaccounts()->sync($data['subaccount_ids'] ?? []);

        return back()->with('success', 'Alcance presupuestal del usuario actualizado correctamente.');
    }

    public function syncPositions(BudgetProfileHomologationService $service): RedirectResponse
    {
        $service->syncEmployeePositions();

        return back()->with('success', 'Puestos activos sincronizados desde empleados.');
    }

    private function validateProfile(Request $request, ?BudgetProfile $profile = null): array
    {
        return $request->validate([
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
