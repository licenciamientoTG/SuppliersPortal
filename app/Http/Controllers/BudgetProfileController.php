<?php

namespace App\Http\Controllers;

use App\Models\BudgetProfile;
use App\Models\Department;
use App\Models\Subaccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BudgetProfileController extends Controller
{
    public function index(): View
    {
        $actor = Auth::user();
        $isSuperAdmin = $actor->hasRole('superadmin');
        $canManageDepartments = $actor->hasAnyRole(['superadmin', 'catalog_admin']);
        $managedDepartmentIds = $isSuperAdmin
            ? null
            : Department::query()->where('manager_user_id', $actor->id)->pluck('id');
        $canManageProfiles = $isSuperAdmin || $managedDepartmentIds->isNotEmpty();

        $subaccounts = Subaccount::query()
            ->with('account:id,code,name')
            ->active()
            ->orderBy('account_id')
            ->orderBy('name')
            ->get();

        $profilesQuery = BudgetProfile::query()
            ->with(['department:id,name,is_active', 'subaccounts.account', 'users:id,name,email,department_id,is_active'])
            ->withCount(['subaccounts', 'users']);

        if (! $isSuperAdmin) {
            $profilesQuery->whereIn('department_id', $managedDepartmentIds);
        }

        $profiles = $profilesQuery
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();

        $departmentsQuery = Department::query()
            ->with(['budgetProfiles' => fn ($query) => $query->withCount(['subaccounts', 'users'])->orderBy('name'), 'manager:id,name,email']);

        if (! $canManageDepartments && ! $isSuperAdmin) {
            $departmentsQuery->whereIn('id', $managedDepartmentIds);
        }

        $departments = $departmentsQuery
            ->orderBy('name')
            ->get();

        $usersQuery = User::query()
            ->select(['id', 'name', 'email', 'department_id', 'is_active'])
            ->whereNotNull('department_id');

        if (! $isSuperAdmin) {
            $usersQuery->whereIn('department_id', $managedDepartmentIds);
        }

        $users = $usersQuery
            ->orderBy('name')
            ->get();

        $departmentManagers = $canManageDepartments
            ? User::role('department_head')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return view('budget_profiles.index', compact(
            'subaccounts',
            'profiles',
            'departments',
            'users',
            'departmentManagers',
            'canManageProfiles',
            'canManageDepartments',
            'isSuperAdmin'
        ));
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $this->validateProfile($request);
        $this->ensureCanManageProfileDepartment((int) $data['department_id']);
        $data['key'] = $this->makeUniqueKey($data['department_id'], $data['name']);

        DB::transaction(function () use ($request, $data) {
            $profile = BudgetProfile::create($data);
            $profile->subaccounts()->sync($request->input('subaccount_ids', []));
            $this->syncProfileUsers($profile, $request->input('user_ids', []));
        });

        return back()->with('success', 'Perfil presupuestal creado correctamente.');
    }

    public function updateProfile(Request $request, BudgetProfile $budgetProfile): RedirectResponse
    {
        $this->ensureCanManageProfile($budgetProfile);
        $data = $this->validateProfile($request, $budgetProfile);
        abort_unless(Auth::user()->hasRole('superadmin') || (int) $data['department_id'] === (int) $budgetProfile->department_id, 403);
        $data['key'] = $budgetProfile->key;

        DB::transaction(function () use ($request, $data, $budgetProfile) {
            $budgetProfile->update($data);
            $budgetProfile->subaccounts()->sync($request->input('subaccount_ids', []));
            $this->syncProfileUsers($budgetProfile, $request->input('user_ids', []));
        });

        return back()->with('success', 'Perfil presupuestal actualizado correctamente.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $this->ensureCanManageDepartments();
        $data = $this->validateDepartment($request);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['created_by'] = Auth::id();

        Department::create($data);

        return back()->with('success', 'Departamento creado correctamente.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $this->ensureCanManageDepartments();
        $data = $this->validateDepartment($request, $department);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $department->update($data);

        return back()->with('success', 'Departamento actualizado correctamente.');
    }

    public function toggleProfile(BudgetProfile $budgetProfile): RedirectResponse
    {
        $this->ensureCanManageProfile($budgetProfile);
        $budgetProfile->update(['is_active' => ! $budgetProfile->is_active]);

        return back()->with('success', $budgetProfile->is_active
            ? 'Perfil presupuestal activado.'
            : 'Perfil presupuestal desactivado. Sus usuarios ya no tienen acceso por este perfil.');
    }

    public function destroyProfile(BudgetProfile $budgetProfile): RedirectResponse
    {
        $this->ensureCanManageProfile($budgetProfile);
        if ($budgetProfile->users()->exists()) {
            return back()->with('error', 'No puedes eliminar un perfil con usuarios asignados. Desactívalo o retira primero las asignaciones.');
        }

        $budgetProfile->delete();

        return back()->with('success', 'Perfil presupuestal eliminado definitivamente.');
    }

    public function toggleDepartment(Department $department): RedirectResponse
    {
        $this->ensureCanManageDepartments();
        $department->update(['is_active' => ! $department->is_active]);

        return back()->with('success', $department->is_active
            ? 'Departamento activado.'
            : 'Departamento desactivado. Sus perfiles dejan de otorgar acceso.');
    }

    public function destroyDepartment(Department $department): RedirectResponse
    {
        $this->ensureCanManageDepartments();
        if ($department->budgetProfiles()->exists() || User::query()->where('department_id', $department->id)->exists()) {
            return back()->with('error', 'No puedes eliminar un departamento con perfiles o usuarios relacionados. Desactívalo primero.');
        }

        $department->delete();

        return back()->with('success', 'Departamento eliminado definitivamente.');
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
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]) + ['is_active' => false];
    }

    private function validateDepartment(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
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
            'manager_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! User::role('department_head')->whereKey($data['manager_user_id'])->exists()) {
            throw ValidationException::withMessages([
                'manager_user_id' => 'El encargado debe tener el rol Jefe de Departamento.',
            ]);
        }

        return $data;
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

    private function syncProfileUsers(BudgetProfile $profile, array $userIds): void
    {
        $userIds = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();
        $validUserIds = User::query()
            ->where('department_id', $profile->department_id)
            ->whereIn('id', $userIds)
            ->pluck('id');

        if ($validUserIds->count() !== $userIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Todos los usuarios asignados deben pertenecer al departamento del perfil.',
            ]);
        }

        $profile->users()->sync($validUserIds);
    }

    private function ensureCanManageDepartments(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['superadmin', 'catalog_admin']), 403);
    }

    private function ensureCanManageProfile(BudgetProfile $profile): void
    {
        $this->ensureCanManageProfileDepartment((int) $profile->department_id);
    }

    private function ensureCanManageProfileDepartment(int $departmentId): void
    {
        $actor = Auth::user();
        $allowed = $actor?->hasRole('superadmin')
            || Department::query()->whereKey($departmentId)->where('manager_user_id', $actor?->id)->exists();

        abort_unless($allowed, 403);
    }
}
