<?php

namespace App\Http\Controllers;

use App\Models\AuthorizerException;
use App\Models\AuthorizerRole;
use App\Models\BudgetProfile;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\User;
use App\Models\UserAuthorizerRole;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return view('users.staff.index');
    }

    public function SupplierIndex(Request $request)
    {
        return view('users.suppliers.index');
    }

    // En tu UserController
    public function datatable(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $search = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'asc';

        // Índices de columnas (ya incluye 'empresas')
        $columns = [
            0 => 'id',
            1 => 'name',
            2 => 'email',
            3 => 'phone',
            4 => 'job_title',
            5 => 'empresas',
            6 => 'roles',
            7 => 'is_active',
            8 => 'acciones',
        ];

        // Query base
        $query = User::with(['roles:id,name', 'companies:id,name,code', 'costCenters:id,name,code,company_id']);

        $recordsTotal = User::count();

        // Búsqueda global
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('job_title', 'LIKE', "%{$search}%")
                    ->orWhereHas('companies', function ($qc) use ($search) {
                        $qc->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
                    });
            });
        }

        $recordsFiltered = $query->count();

        // Ordenamiento
        $orderable = $columns[$orderColumn] ?? 'id';
        if (! in_array($orderable, ['empresas', 'roles', 'acciones'])) {
            $query->orderBy($orderable, $orderDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        // Paginación
        $users = $query->skip($start)->take($length)->get();

        // Paleta de colores por rol
        $roleColors = [
            'super-admin' => 'dark',
            'admin' => 'primary',
            'manager' => 'info',
            'editor' => 'warning',
            'staff' => 'secondary',
            'supplier' => 'success',
        ];

        // Formatear respuesta
        $data = $users->map(function ($user) use ($roleColors) {

            // === BADGES DE ROLES ===
            $rolesHtml = $user->roles->pluck('name')->map(function ($name) use ($roleColors) {
                $color = $roleColors[$name] ?? 'secondary';

                return '<span class="badge bg-'.$color.' me-1">'.e(trans("roles.{$name}")).'</span>';
            })->implode('');

            // === BADGES DE EMPRESAS ===
            $empresasHtml = '';
            if ($user->companies->isNotEmpty()) {

                // Tooltip con lista completa
                $tooltip = $user->companies->map(fn ($c) => '['.$c->code.'] '.$c->name)->implode("\n");

                // Mostrar solo las primeras 3 empresas
                $badges = $user->companies->take(3)->map(function ($c) {
                    return '<span class="badge bg-light text-dark border me-1">['.e($c->code).']</span>';
                })->implode('');

                // Si hay más de 3, mostrar contador adicional
                if ($user->companies->count() > 3) {
                    $badges .= '<span class="badge bg-secondary">+'.($user->companies->count() - 3).'</span>';
                }

                // Tooltip Bootstrap con saltos de línea
                $empresasHtml = '<span data-bs-toggle="tooltip" data-bs-placement="top"
                                    data-bs-html="true"
                                    title="'.e(nl2br($tooltip)).'">'.$badges.'</span>';
            }

            return [
                'id' => $user->id,
                'name' => e($user->name),
                'email' => e($user->email),
                'telefono' => e($user->phone),
                'puesto' => e($user->job_title),
                'empresas' => $empresasHtml,
                'centros_costo' => $this->formatCostCentersHtml($user),
                'roles' => $rolesHtml,
                'activo' => $user->is_active,
                'acciones' => view('users.staff.partials.actions', compact('user'))->render(),
            ];
        });

        // === RESPUESTA JSON PARA DATATABLES ===
        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    // Nuevo método helper:
    private function formatCostCentersHtml($user)
    {
        if ($user->costCenters->isEmpty()) {
            return '<span class="text-muted">—</span>';
        }

        // Agrupar por compañía para el tooltip
        $grouped = $user->costCenters->groupBy('company_id');
        $tooltipHtml = '';

        foreach ($grouped as $companyId => $centers) {
            $company = $user->companies->firstWhere('id', $companyId);
            if ($company) {
                $tooltipHtml .= '<strong>['.$company->code.']</strong><br>';
                $tooltipHtml .= $centers->pluck('name')->implode('<br>');
                $tooltipHtml .= '<br><br>';
            }
        }

        // Mostrar solo los primeros 2 centros
        $badges = $user->costCenters->take(2)->map(function ($cc) {
            $badge = $cc->is_default ? 'bg-primary' : 'bg-light text-dark border';

            return '<span class="badge '.$badge.' me-1">'.e($cc->code).'</span>';
        })->implode('');

        // Contador si hay más
        if ($user->costCenters->count() > 2) {
            $badges .= '<span class="badge bg-secondary">+'.($user->costCenters->count() - 2).'</span>';
        }

        return '<span data-bs-toggle="tooltip" data-bs-placement="top"
                  data-bs-html="true"
                  title="'.$tooltipHtml.'">'.$badges.'</span>';
    }

    public function costCentersForm(User $user)
    {
        // Cargar compañías del usuario con sus centros de costo
        $user->load(['companies.costCenters']);

        // Obtener IDs de centros de costo ya asignados al usuario
        $assignedCostCenterIds = $user->costCenters()->pluck('cost_center_id')->toArray();

        return view('users.staff.partials.cost-centers-form', compact('user', 'assignedCostCenterIds'));
    }

    public function costCentersStore(Request $request, User $user)
    {
        $validated = $request->validate([
            'cost_centers' => 'nullable|array',
            'cost_centers.*' => 'exists:cost_centers,id',
            'default_cost_center' => 'nullable|exists:cost_centers,id',
        ]);

        // Sincronizar centros de costo
        $syncData = [];
        foreach ($validated['cost_centers'] ?? [] as $costCenterId) {
            $syncData[$costCenterId] = [
                'is_default' => ($costCenterId == $validated['default_cost_center']),
                'is_active' => true,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $user->costCenters()->sync($syncData);

        return response()->json(['success' => true]);
    }

    public function create()
    {
        $user = new User;
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $authorizerRoles = AuthorizerRole::where('is_active', true)->orderBy('display_order')->orderBy('name')->get(['id', 'name']);
        $departments = Department::active()->orderBy('name')->get(['id', 'name']);
        $budgetProfiles = BudgetProfile::active()->with('department:id,name')->orderBy('department_id')->orderBy('name')->get(['id', 'department_id', 'name']);

        if (request()->ajax()) {
            return view('users.staff.partials.form', [
                'user' => $user,
                'roles' => $roles,
                'authorizerRoles' => $authorizerRoles,
                'departments' => $departments,
                'budgetProfiles' => $budgetProfiles,
                'userBudgetProfiles' => collect(),
                'userRoles' => collect(),
                'mode' => 'create',
                'action' => route('users.store'),
                'method' => 'POST',
                'title' => 'Crear usuario',
            ]);
        }

        return view('users.staff.partials.form', compact('user', 'roles', 'authorizerRoles', 'departments', 'budgetProfiles') + [
            'userRoles' => collect(),
            'userBudgetProfiles' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'budget_profile_ids' => ['nullable', 'array'],
            'budget_profile_ids.*' => ['integer', 'exists:budget_profiles,id'],
            'is_active' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'authorizer_role_id' => ['nullable', 'exists:authorizer_roles,id'],
            'authorization_exception_enabled' => ['nullable', 'boolean'],
            'authorization_exception_limit' => ['nullable', 'numeric', 'min:0'],
            'authorization_exception_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (blank($validated['name'])) {
            $validated['name'] = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        DB::transaction(function () use ($request, $validated) {
            $user = User::create(Arr::except($validated, ['avatar', 'roles', 'budget_profile_ids']));

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store("users/{$user->id}/avatar", 'public');
                $user->update(['avatar' => $path]);
            }

            if (! empty($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            $this->syncBudgetProfiles($user, $validated);
            $this->syncAuthorizerData($user, $validated);
        });

        return redirect()->route('users.staff.index')->with('success', 'Usuario creado correctamente.');
    }

    public function show(User $user)
    {
        $user->load(['roles.permissions', 'companies', 'costCenters', 'employee', 'authorizerAssignment.authorizerRole', 'activeAuthorizerException']);

        return view('users.staff.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $authorizerRoles = AuthorizerRole::where('is_active', true)->orderBy('display_order')->orderBy('name')->get(['id', 'name']);
        $departments = Department::active()->orderBy('name')->get(['id', 'name']);
        $budgetProfiles = BudgetProfile::active()->with('department:id,name')->orderBy('department_id')->orderBy('name')->get(['id', 'department_id', 'name']);
        $userRoles = $user->roles->pluck('name');
        $user->loadMissing('authorizerAssignment.authorizerRole', 'activeAuthorizerException', 'budgetProfiles:id');
        $userBudgetProfiles = $user->budgetProfiles->pluck('id');

        return view('users.staff.partials.form', [
            'user' => $user,
            'roles' => $roles,
            'authorizerRoles' => $authorizerRoles,
            'departments' => $departments,
            'budgetProfiles' => $budgetProfiles,
            'userRoles' => $userRoles,
            'userBudgetProfiles' => $userBudgetProfiles,
            'mode' => 'edit',
            'action' => route('users.update', $user),
            'method' => 'PUT',
            'title' => 'Editar usuario',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'budget_profile_ids' => ['nullable', 'array'],
            'budget_profile_ids.*' => ['integer', 'exists:budget_profiles,id'],
            'is_active' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'remove_avatar' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'authorizer_role_id' => ['nullable', 'exists:authorizer_roles,id'],
            'authorization_exception_enabled' => ['nullable', 'boolean'],
            'authorization_exception_limit' => ['nullable', 'numeric', 'min:0'],
            'authorization_exception_reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'name' => 'nombre',
            'email' => 'correo',
        ]);

        $removeAvatar = (bool) ($validated['remove_avatar'] ?? false);
        $newAvatar = $request->file('avatar');

        DB::transaction(function () use ($user, $validated, $removeAvatar, $newAvatar) {

            $user->first_name = $validated['first_name'] ?? $user->first_name;
            $user->last_name = $validated['last_name'] ?? $user->last_name;
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->phone = $validated['phone'] ?? $user->phone;
            $user->job_title = $validated['job_title'] ?? $user->job_title;
            $user->department_id = $validated['department_id'] ?? null;
            $user->is_active = (bool) ($validated['is_active'] ?? false);

            if (! empty($validated['password'])) {
                $user->password = bcrypt($validated['password']);
            }

            if ($removeAvatar && $user->avatar) {
                Storage::disk('public')->delete($user->avatar);
                $user->avatar = null;
            }

            if ($newAvatar) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $user->avatar = $newAvatar->store("users/{$user->id}/avatar", 'public');
            }

            $user->save();

            $user->syncRoles($validated['roles'] ?? []);
            $this->syncBudgetProfiles($user, $validated);
            $this->syncAuthorizerData($user, $validated);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Usuario actualizado correctamente.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'job_title' => $user->job_title,
                    'is_active' => (bool) $user->is_active,
                    'avatar_url' => $user->avatar
                        ? asset('storage/'.$user->avatar)
                        : null,
                ],
            ]);
        }

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    private function syncAuthorizerData(User $user, array $validated): void
    {
        $technicalRoles = $validated['roles'] ?? [];
        $hasAuthorizerTechnicalRole = in_array('authorizer', $technicalRoles, true);

        if (! $hasAuthorizerTechnicalRole) {
            UserAuthorizerRole::where('user_id', $user->id)->delete();

            AuthorizerException::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'ends_at' => now()]);

            return;
        }

        $roleId = $validated['authorizer_role_id'] ?? null;

        if ($roleId) {
            UserAuthorizerRole::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'authorizer_role_id' => $roleId,
                    'assigned_by' => Auth::id(),
                ]
            );
        } else {
            UserAuthorizerRole::where('user_id', $user->id)->delete();
        }

        $exceptionEnabled = (bool) ($validated['authorization_exception_enabled'] ?? false);

        if (
            $exceptionEnabled
            && isset($validated['authorization_exception_limit'])
            && $validated['authorization_exception_limit'] !== null
            && ! blank($validated['authorization_exception_reason'] ?? null)
        ) {
            AuthorizerException::updateOrCreate(
                ['user_id' => $user->id, 'is_active' => true],
                [
                    'approval_limit' => $validated['authorization_exception_limit'],
                    'reason' => $validated['authorization_exception_reason'],
                    'created_by' => Auth::id(),
                    'starts_at' => now(),
                    'ends_at' => null,
                ]
            );
        } else {
            AuthorizerException::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'ends_at' => now()]);
        }
    }

    private function syncBudgetProfiles(User $user, array $validated): void
    {
        $departmentId = $validated['department_id'] ?? null;

        if (! $departmentId) {
            $user->budgetProfiles()->sync([]);

            return;
        }

        $profileIds = BudgetProfile::query()
            ->where('department_id', $departmentId)
            ->whereIn('id', $validated['budget_profile_ids'] ?? [])
            ->pluck('id')
            ->all();

        $user->budgetProfiles()->sync($profileIds);
    }

    public function toggleActive(User $user)
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        return response()->json(['ok' => true, 'is_active' => $user->is_active]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.staff.index')->with('success', 'Usuario eliminado.');
    }

    public function editRoles(User $user)
    {
        // Trae todos los roles disponibles
        $roles = Role::orderBy('name')->get(['name']);

        // Devuelve solo el fragmento HTML para el modal
        return view('users.staff.partials.roles_form', compact('user', 'roles'));
    }

    public function updateRoles(Request $request, User $user)
    {
        // Validar arreglo de roles por nombre
        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        // Sincroniza (agrega y quita automáticamente)
        $user->syncRoles($data['roles'] ?? []);

        // Respuesta JSON para que tu JS cierre el modal y recargue DataTable
        return response()->json(['ok' => true]);
    }

    /**
     * Endpoint de DataTables server-side SIN Yajra.
     * Devuelve: draw, recordsTotal, recordsFiltered, data.
     *
     * Requisitos/Asunciones:
     * - Tabla relacionada: suppliers (col: user_id, telefono, puesto)
     * - Relación en User: supplier() y roles() (Spatie)
     * - Campo activo en users: is_active|active|status (se detecta)
     */
    public function suppliersDatatable(Request $request)
    {
        $draw = intval($request->input('draw', 1));
        $start = intval($request->input('start', 0));
        $length = intval($request->input('length', 10));
        $search = trim($request->input('search.value', '')) ?: null;

        $columns = [
            0 => 'id',
            1 => 'company_name',
            2 => 'rfc',
            3 => 'contact_person',
            4 => 'contact_phone',
            5 => 'email',
            6 => 'bank_name',
            7 => 'status',
            8 => 'last_login',  // NUEVA
            9 => 'is_active',
            10 => 'acciones',
        ];
        $orderIdx = intval($request->input('order.0.column', 0));
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderCol = $columns[$orderIdx] ?? 'id';

        // === Query base sobre users con join a suppliers ===
        $base = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email as user_email',
                'users.is_active', // 👈 agregado
                'users.last_login',
                DB::raw('COALESCE(users.is_active, 0) as activo_flag'),
                's.id as supplier_id',
                's.company_name',
                's.rfc',
                's.contact_person',
                's.contact_phone',
                's.email',
                's.bank_name',
                's.status',
            ])
            ->join('suppliers as s', 's.user_id', '=', 'users.id');

        $recordsTotal = (clone $base)->count('users.id');

        // === Búsqueda global ===
        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('s.company_name', 'like', "%{$search}%")
                    ->orWhere('s.rfc', 'like', "%{$search}%")
                    ->orWhere('s.contact_person', 'like', "%{$search}%")
                    ->orWhere('s.contact_phone', 'like', "%{$search}%")
                    ->orWhere('s.email', 'like', "%{$search}%")
                    ->orWhere('s.bank_name', 'like', "%{$search}%")
                    ->orWhere('s.status', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count('users.id');

        // === Ordenamiento dinámico ===
        switch ($orderCol) {
            case 'company_name':
                $base->orderBy('s.company_name', $orderDir);
                break;
            case 'rfc':
                $base->orderBy('s.rfc', $orderDir);
                break;
            case 'contact_person':
                $base->orderBy('s.contact_person', $orderDir);
                break;
            case 'contact_phone':
                $base->orderBy('s.contact_phone', $orderDir);
                break;
            case 'email':
                $base->orderBy('s.email', $orderDir);
                break;
            case 'bank_name':
                $base->orderBy('s.bank_name', $orderDir);
                break;
            case 'status':
                $base->orderBy('s.status', $orderDir);
                break;
            default:
                $base->orderBy('users.id', $orderDir);
        }

        // === Paginación ===
        $rows = $base->skip($start)->take($length)->get();

        // === Roles (Spatie) ===
        $userIds = $rows->pluck('id')->all();
        $rolesByUser = [];
        if ($userIds) {
            $roles = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_type', '=', User::class)
                ->whereIn('mhr.model_id', $userIds)
                ->select('mhr.model_id as user_id', 'r.name as role_name')
                ->get()
                ->groupBy('user_id');

            foreach ($roles as $uid => $list) {
                $rolesByUser[$uid] = $list->pluck('role_name')->all();
            }
        }

        // === Construir respuesta ===
        $data = $rows->map(function ($row) use ($rolesByUser) {
            $rolesArr = $rolesByUser[$row->id] ?? [];
            $rolesHtml = empty($rolesArr)
                ? '<span class="text-muted">—</span>'
                : collect($rolesArr)->map(fn ($r) => '<span class="badge bg-secondary me-1">'.e(trans("roles.{$r}")).'</span>')->implode(' ');

            $editUrl = route('users.suppliers.edit', $row->id);
            $toggleUrl = route('users.suppliers.toggle', $row->id);
            $delUrl = route('users.suppliers.destroy', $row->id);
            $docsUrl = route('admin.review.suppliers.show', $row->supplier_id);

            $acciones = <<<HTML
                <div class="d-flex justify-content-end gap-1">
                    <a href="{$editUrl}" class="btn btn-sm btn-outline-primary js-open-user-modal" data-url="{$editUrl}" title="Editar"><i class="ti ti-pencil"></i></a>
                    <a href="#" class="btn btn-sm btn-outline-secondary js-toggle-active" data-url="{$toggleUrl}" title="Activar/Desactivar"><i class="ti ti-switch-2"></i></a>
                    <a href="{$docsUrl}" class="btn btn-sm btn-outline-info" target="_blank" title="Revisar documentos"><i class="ti ti-file-description"></i></a>
                    <a href="#" class="btn btn-sm btn-outline-danger js-delete-user" data-url="{$delUrl}" data-name="{$row->company_name}" title="Eliminar"><i class="ti ti-trash"></i></a>
                </div>
                HTML;

            return [
                'id' => $row->id,
                'company_name' => $row->company_name,
                'rfc' => $row->rfc,
                'contact_person' => $row->contact_person,
                'contact_phone' => $row->contact_phone,
                'email' => $row->email,
                'bank_name' => $row->bank_name,
                'last_login' => optional($row->last_login)->format('Y-m-d H:i'), // 👈 NUEVO
                'status' => match ($row->status) {
                    'pending_docs' => '
                        <span class="badge bg-warning text-dark">
                            <i class="ti ti-file-text me-1"></i> En revisión
                        </span>',
                    'approved' => '
                        <span class="badge bg-success">
                            <i class="ti ti-check me-1"></i> Aprobado
                        </span>',
                    'rejected' => '
                        <span class="badge bg-danger">
                            <i class="ti ti-x me-1"></i> Rechazado
                        </span>',
                    default => '
                        <span class="badge bg-secondary">
                            <i class="ti ti-question-mark me-1"></i> '.$row->status.'
                        </span>',
                },
                'is_active' => (bool) $row->is_active,
                'acciones' => $acciones,
            ];
        })->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function toggleSupplier(User $user)
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        return response()->json([
            'message' => $user->is_active
                ? 'Usuario activado correctamente'
                : 'Usuario desactivado correctamente',
        ]);
    }

    public function destroySupplier(User $user)
    {
        $name = $user->supplier->company_name ?? $user->name;
        $user->delete();

        return response()->json([
            'message' => "El proveedor {$name} fue eliminado correctamente",
        ]);
    }

    public function editSupplier(User $user)
    {
        $user->load('supplier');

        // Opciones para selects
        $statuses = ['Pending_docs' => 'Documentos en revisión', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];
        $currencies = ['MXN' => 'MXN (Peso Mexicano)', 'USD' => 'USD (Dólar USA)'];

        return view('users.suppliers.partials.form', compact('user', 'statuses', 'currencies'));
    }

    public function updateSupplier(Request $request, User $user)
    {
        $user->load('supplier');
        $supplierId = optional($user->supplier)->id;

        // =====================================================================
        // VALIDACIONES
        // =====================================================================
        $request->validate([
            // --- Usuario ---
            'user.name' => ['required', 'string', 'max:150'],
            'user.email' => ['required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user->id)],
            'user.job_title' => ['nullable', 'string', 'max:100'],
            'user.is_active' => ['nullable', 'boolean'],
            'user.avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // --- Empresa / Contacto ---
            'supplier.company_name' => ['required', 'string', 'max:255'],
            'supplier.rfc' => ['required', 'string', 'max:13',
                Rule::unique('suppliers', 'rfc')->ignore($supplierId)],
            'supplier.phone_number' => ['nullable', 'string', 'max:15'],
            'supplier.contact_person' => ['nullable', 'string', 'max:100'],
            'supplier.contact_phone' => ['nullable', 'string', 'max:10'],
            'supplier.email' => ['nullable', 'email', 'max:150'],
            'supplier.address' => ['nullable', 'string', 'max:500'],
            'supplier.postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'supplier.supplier_type' => ['nullable', 'in:product,service,product_service'],
            'supplier.currency' => ['nullable', 'string', 'max:3'],
            'supplier.accepted_currencies' => ['required', 'array', 'min:1'],
            'supplier.accepted_currencies.*' => ['string', Rule::in(['MXN', 'USD'])],
            'supplier.person_type' => ['nullable', 'in:fisica,moral,extranjero'],
            'supplier.status' => ['nullable', 'in:pending_docs,approved,rejected'],
            'supplier.tax_regimes' => ['nullable', 'array'],
            'supplier.tax_regimes.*' => ['string'],
            'supplier.economic_activity' => ['nullable', 'array'],
            'supplier.economic_activity.*' => ['nullable', 'string', 'max:150'],
            'supplier.default_payment_terms' => ['nullable', Rule::in(array_column(\App\Enum\PaymentTerm::cases(), 'value'))],

            // --- Bancario MX ---
            'supplier.bank_name' => ['nullable', 'string', 'max:100'],
            'supplier.account_number' => ['nullable', 'string', 'max:20'],
            'supplier.clabe' => ['nullable', 'digits:18'],

            // --- Bancario Internacional ---
            'supplier.us_bank_name' => ['nullable', 'string', 'max:100'],
            'supplier.swift_bic' => ['nullable', 'string', 'min:8', 'max:11'],
            'supplier.aba_routing' => ['nullable', 'digits:9'],
            'supplier.iban' => ['nullable', 'string', 'max:34'],
            'supplier.bank_address' => ['nullable', 'string', 'max:255'],

            // --- REPSE ---
            'supplier.provides_specialized_services' => ['nullable', 'boolean'],
            'supplier.repse_registration_number' => [
                'nullable', 'string',
                Rule::requiredIf(fn () => (bool) $request->input('supplier.provides_specialized_services')),
            ],
            'supplier.repse_expiry_date' => [
                'nullable', 'date',
                Rule::requiredIf(fn () => (bool) $request->input('supplier.provides_specialized_services')),
            ],
            'supplier.specialized_services_types' => ['nullable', 'array'],
            'supplier.specialized_services_types.*' => ['string'],
        ], [
            // Mensajes personalizados
            'user.name.required' => 'El nombre del usuario es obligatorio.',
            'user.email.required' => 'El correo electrónico es obligatorio.',
            'user.email.unique' => 'Este correo ya está registrado en otro usuario.',
            'user.avatar.image' => 'El avatar debe ser una imagen.',
            'user.avatar.max' => 'El avatar no debe superar 2 MB.',
            'supplier.company_name.required' => 'La razón social es obligatoria.',
            'supplier.rfc.required' => 'El RFC es obligatorio.',
            'supplier.rfc.unique' => 'Este RFC ya está registrado en otro proveedor.',
            'supplier.email.email' => 'El correo del proveedor no tiene un formato válido.',
            'supplier.clabe.digits' => 'La CLABE debe tener exactamente 18 dígitos.',
            'supplier.aba_routing.digits' => 'El ABA/Routing Number debe tener exactamente 9 dígitos.',
            'supplier.swift_bic.min' => 'El código SWIFT/BIC debe tener entre 8 y 11 caracteres.',
            'supplier.swift_bic.max' => 'El código SWIFT/BIC debe tener entre 8 y 11 caracteres.',
            'supplier.default_payment_terms.in' => 'Las condiciones de pago seleccionadas no son válidas.',
            'supplier.accepted_currencies.required' => 'Debe seleccionar al menos una moneda que maneja el proveedor.',
            'supplier.accepted_currencies.min' => 'Debe seleccionar al menos una moneda que maneja el proveedor.',
            'supplier.accepted_currencies.*.in' => 'Solo se permiten las monedas MXN o USD.',
            'supplier.repse_registration_number.required' => 'El número de registro REPSE es obligatorio cuando el proveedor presta servicios especializados.',
            'supplier.repse_expiry_date.required' => 'La vigencia REPSE es obligatoria cuando el proveedor presta servicios especializados.',
        ]);

        // =====================================================================
        // GUARDAR USUARIO
        // =====================================================================
        $user->fill([
            'name' => $request->input('user.name'),
            'email' => $request->input('user.email'),
            'job_title' => $request->input('user.job_title'),
        ]);
        $user->is_active = (bool) $request->input('user.is_active', false);

        // --- Avatar ---
        $remove = (bool) $request->input('user.remove_avatar', false);

        if ($remove && $user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->avatar = null;
        }

        if ($request->hasFile('user.avatar')) {
            /** @var UploadedFile $avatar */
            $avatar = $request->file('user.avatar');

            if ($avatar->isValid()) {
                $path = $avatar->store('avatars', 'public');
                if ($user->avatar && $user->avatar !== $path) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $user->avatar = $path;
            }
        }

        $user->save();

        // =====================================================================
        // GUARDAR PROVEEDOR
        // =====================================================================
        $providesSpecialized = (bool) $request->input('supplier.provides_specialized_services', false);

        $supplierData = [
            // Empresa / Contacto
            'company_name' => $request->input('supplier.company_name'),
            'rfc' => strtoupper((string) $request->input('supplier.rfc')),
            'phone_number' => $request->input('supplier.phone_number'),
            'contact_person' => $request->input('supplier.contact_person'),
            'contact_phone' => $request->input('supplier.contact_phone'),
            'email' => $request->input('supplier.email'),
            'address' => $request->input('supplier.address'),
            'postal_code' => $request->filled('supplier.postal_code')
                ? preg_replace('/\D/', '', (string) $request->input('supplier.postal_code'))
                : null,
            'supplier_type' => $request->input('supplier.supplier_type'),
            'currency' => $request->input('supplier.currency', 'MXN'),
            'accepted_currencies' => $request->input('supplier.accepted_currencies', []),
            'person_type' => $request->input('supplier.person_type'),
            'status' => $request->input('supplier.status', $user->supplier->status ?? 'pending_docs'),
            'tax_regimes' => SupplierFiscalCatalog::normalizeSelectedRegimes(
                $request->input('supplier.person_type'),
                $request->input('supplier.tax_regimes', [])
            ),
            'economic_activity' => SupplierFiscalCatalog::normalizeActivities(
                $request->input('supplier.economic_activity', [])
            ),
            'default_payment_terms' => $request->input('supplier.default_payment_terms', 'CASH'),

            // Bancario MX
            'bank_name' => $request->input('supplier.bank_name'),
            'account_number' => $request->input('supplier.account_number') ?: null,
            'clabe' => $request->input('supplier.clabe') ?: null,

            // Bancario Internacional
            'us_bank_name' => $request->input('supplier.us_bank_name'),
            'swift_bic' => $request->input('supplier.swift_bic')
                                    ? strtoupper($request->input('supplier.swift_bic'))
                                    : null,
            'aba_routing' => $request->input('supplier.aba_routing') ?: null,
            'iban' => $request->input('supplier.iban')
                                    ? strtoupper($request->input('supplier.iban'))
                                    : null,
            'bank_address' => $request->input('supplier.bank_address'),

            // REPSE (los campos condicionales se limpian cuando el toggle está desactivado)
            'provides_specialized_services' => $providesSpecialized,
            'repse_registration_number' => $providesSpecialized
                                                ? $request->input('supplier.repse_registration_number')
                                                : null,
            'repse_expiry_date' => $providesSpecialized
                                                ? $request->input('supplier.repse_expiry_date')
                                                : null,
            // El modelo tiene cast 'array', Laravel serializa el array a JSON automáticamente
            'specialized_services_types' => $providesSpecialized
                                                ? ($request->input('supplier.specialized_services_types') ?? [])
                                                : [],
        ];

        if (($supplierData['person_type'] ?? null) === 'extranjero') {
            $supplierData['tax_regimes'] = [];
        }

        $user->supplier
            ? $user->supplier->update($supplierData)
            : $user->supplier()->create($supplierData);

        return response()->json(['message' => 'Proveedor actualizado correctamente.']);
    }

    public function editCompanies(User $user)
    {
        $companies = Company::orderBy('name')->get(['id', 'code', 'name']);
        $attached = $user->companies()->pluck('companies.id')->all();

        return view('users.staff.partials.companies', compact('user', 'companies', 'attached'));
    }

    public function updateCompanies(Request $request, User $user)
    {
        // company_ids[] llega del <select multiple> o checkboxes
        $ids = $request->input('company_ids', []);
        // Normaliza a enteros por seguridad
        $ids = array_map('intval', $ids);

        $user->companies()->sync($ids);

        return response()->json(['success' => true]);
    }

    /**
     * Mostrar formulario de asignación de centros de costo.
     * Solo muestra centros de costo de las compañías que el usuario ya tiene asignadas.
     */
    public function editCostCenters(User $user)
    {
        // Cargar relaciones necesarias
        $user->load([
            'companies.costCenters' => function ($query) {
                $query->orderBy('code');
            },
            'costCenters',
        ]);

        // Obtener IDs de centros de costo ya asignados al usuario
        $assignedCostCenters = $user->costCenters->keyBy('id');

        // Verificar si el usuario tiene compañías asignadas
        if ($user->companies->isEmpty()) {
            return response()->json([
                'error' => true,
                'message' => 'El usuario no tiene compañías asignadas. Primero asigna compañías.',
            ], 422);
        }

        return view('users.staff.partials.cost-centers-form', compact('user', 'assignedCostCenters'));
    }

    /**
     * Actualizar centros de costo del usuario.
     * Validar que los centros pertenezcan a las compañías del usuario.
     */
    public function updateCostCenters(Request $request, User $user)
    {
        // Validar entrada
        $validated = $request->validate([
            'cost_centers' => 'nullable|array',
            'cost_centers.*' => 'exists:cost_centers,id',
            'default_cost_center' => 'nullable|exists:cost_centers,id',
        ], [
            'cost_centers.*.exists' => 'Uno o más centros de costo no son válidos.',
            'default_cost_center.exists' => 'El centro de costo predeterminado no es válido.',
        ]);

        try {
            DB::beginTransaction();

            // Obtener IDs de compañías del usuario
            $userCompanyIds = $user->companies()->pluck('companies.id')->toArray();

            // Validar que todos los centros de costo pertenezcan a las compañías del usuario
            if (! empty($validated['cost_centers'])) {
                $invalidCostCenters = CostCenter::whereIn('id', $validated['cost_centers'])
                    ->whereNotIn('company_id', $userCompanyIds)
                    ->exists();

                if ($invalidCostCenters) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Algunos centros de costo no pertenecen a las compañías del usuario.',
                    ], 422);
                }
            }

            // Preparar datos para sincronización
            $syncData = [];
            $defaultCostCenter = $validated['default_cost_center'] ?? null;

            foreach ($validated['cost_centers'] ?? [] as $costCenterId) {
                $syncData[$costCenterId] = [
                    'is_default' => ($costCenterId == $defaultCostCenter),
                    'is_active' => true,
                    'created_by' => $user->costCenters->contains($costCenterId)
                        ? $user->costCenters->find($costCenterId)->pivot->created_by
                        : Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => $user->costCenters->contains($costCenterId)
                        ? $user->costCenters->find($costCenterId)->pivot->created_at
                        : now(),
                    'updated_at' => now(),
                ];
            }

            // Sincronizar centros de costo
            $user->costCenters()->sync($syncData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Centros de costo actualizados correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al actualizar centros de costo del usuario', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Error al actualizar centros de costo: '.$e->getMessage(),
            ], 500);
        }
    }
}
