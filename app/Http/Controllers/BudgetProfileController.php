<?php

namespace App\Http\Controllers;

use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\Subaccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
        $data['key'] = $this->makeUniqueKey($data['department_id'], $data['name']);

        $profile = BudgetProfile::create($data);
        $profile->subaccounts()->sync($request->input('subaccount_ids', []));

        return back()->with('success', 'Perfil presupuestal creado correctamente.');
    }

    public function updateProfile(Request $request, BudgetProfile $budgetProfile): RedirectResponse
    {
        $data = $this->validateProfile($request, $budgetProfile);
        $data['key'] = $budgetProfile->key;

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

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $this->validateDepartment($request);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['created_by'] = Auth::id();

        Department::create($data);

        return back()->with('success', 'Departamento creado correctamente.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validateDepartment($request, $department);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $department->update($data);

        return back()->with('success', 'Departamento actualizado correctamente.');
    }

    private function validateProfile(Request $request, ?BudgetProfile $profile = null): array
    {
        return $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'key' => [
                'nullable',
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

    private function validateDepartment(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departments', 'name')->ignore($department?->id),
            ],
            'abbreviated' => [
                'required',
                'string',
                'max:10',
                Rule::unique('departments', 'abbreviated')->ignore($department?->id),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function makeUniqueKey(int $departmentId, string $name): string
    {
        $department = Department::query()->find($departmentId);
        $base = Str::of($department?->name.' '.$name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit(70, '')
            ->value();

        $base = $base !== '' ? $base : 'perfil_presupuestal';
        $key = $base;
        $suffix = 2;

        while (BudgetProfile::query()->where('key', $key)->exists()) {
            $key = Str::limit($base, 75 - strlen((string) $suffix), '').'_'.$suffix;
            $suffix++;
        }

        return $key;
    }
}
