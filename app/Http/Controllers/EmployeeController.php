<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeEvent;
use App\Models\User;
use App\Notifications\StaffWelcomeNotification;
use App\Rules\AllowedEmailDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    /**
     * Campos rastreados para eventos, en orden.
     * clave => etiqueta legible en español
     */
    private const CAMPOS_RASTREADOS = [
        'archivo_origen'     => 'archivo de origen',
        'first_name'         => 'nombre',
        'last_name'          => 'apellidos',
        'department'         => 'departamento',
        'job_title'          => 'puesto',
        'hire_date'          => 'fecha de ingreso',
        'is_active'          => 'estado activo',
        'termination_date'   => 'fecha de baja',
        'rehire_eligible'    => 'recontratable',
        'termination_reason' => 'motivo de baja',
        'team'               => 'equipo',
        'seniority'          => 'antigüedad',
        'rfc'                => 'RFC',
        'imss'               => 'IMSS',
        'curp'               => 'CURP',
        'gender'             => 'género',
        'phone'              => 'teléfono',
        'address'            => 'dirección',
        'email'              => 'correo electrónico',
        'education'          => 'estudios',
        'company'            => 'empresa',
        'responsible'        => 'responsable',
        'leader'             => 'líder',
        'vacation_balance'   => 'saldo de vacaciones',
        'savings_fund'       => 'fondo de ahorro',
        'daily_salary'       => 'salario diario',
        'severance_bonus'    => 'gratificación por separación',
        'indemnization'      => 'indemnización',
        'seniority_premium'  => 'prima de antigüedad',
    ];

    public function photoForm(Employee $employee): View
    {
        return view('employees.partials.photo-form', compact('employee'));
    }

    public function uploadPhoto(Employee $employee, Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:3072', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }

        $path = $request->file('photo')->store("employees/{$employee->id}/photo", 'public');
        $employee->update(['photo' => $path]);

        return response()->json([
            'success'   => true,
            'photo_url' => Storage::url($path),
        ]);
    }

    public function promoteForm(Employee $employee): JsonResponse|View
    {
        if ($employee->user_id !== null) {
            return response()->json(['error' => 'Este empleado ya tiene un usuario asignado.'], 409);
        }

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return view('employees.partials.promote-form', compact('employee', 'roles'));
    }

    public function promote(Employee $employee): JsonResponse
    {
        if ($employee->user_id !== null) {
            return response()->json(['error' => 'Este empleado ya tiene un usuario asignado.'], 409);
        }

        $validated = request()->validate([
            'name'      => ['required', 'string', 'max:180'],
            'email'     => ['required', 'email', 'max:180', 'unique:users,email', new AllowedEmailDomain],
            'password'  => ['required', 'string', 'min:8'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'roles'     => ['nullable', 'array'],
            'roles.*'   => ['string', 'exists:roles,name'],
            'avatar'    => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $plainPassword = $validated['password'];

        DB::transaction(function () use ($validated, $employee, $plainPassword) {
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => $validated['password'],
                'phone'     => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'is_active' => true,
            ]);

            if (request()->hasFile('avatar')) {
                $file = request()->file('avatar');
                $filename = $file->getClientOriginalName();
                $path = $file->storeAs("users/{$user->id}/avatar", $filename, 'public');
                $user->update(['avatar' => $path]);
            } elseif ($employee->photo) {
                $filename = basename($employee->photo);
                $newPath  = "users/{$user->id}/avatar/{$filename}";
                Storage::disk('public')->copy($employee->photo, $newPath);
                $user->update(['avatar' => $newPath]);
            }

            if (!empty($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            $employee->update(['user_id' => $user->id]);

            $user->notify(new StaffWelcomeNotification($plainPassword));
        });

        return response()->json(['success' => true, 'message' => 'Usuario creado y notificado correctamente.']);
    }

    public function index(): View
    {
        $companies = Employee::distinct()->orderBy('company')->pluck('company')->filter()->values();
        return view('employees.index', compact('companies'));
    }

    public function datatable(): JsonResponse
    {
        $query = Employee::query()
            ->select(['id', 'employee_number', 'first_name', 'last_name', 'company', 'department', 'job_title', 'leader', 'is_active', 'user_id', 'photo']);

        return DataTables::of($query)
            ->filter(function ($query) {
                if (request()->filled('is_active')) {
                    $query->where('is_active', request('is_active'));
                }

                if (request()->filled('company')) {
                    $query->where('company', request('company'));
                }

                if (request()->filled('search.value')) {
                    $search = request('search.value');
                    $query->where(function($q) use ($search) {
                        $q->where('employee_number', 'like', "%{$search}%")
                          ->orWhere('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('company', 'like', "%{$search}%")
                          ->orWhere('department', 'like', "%{$search}%")
                          ->orWhere('job_title', 'like', "%{$search}%")
                          ->orWhere('leader', 'like', "%{$search}%")
                          ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                    });
                }
            }, true)
            ->addColumn('photo', function (Employee $row) {
                if ($row->photo) {
                    $url = Storage::url($row->photo);
                    return '<img src="' . e($url) . '"
                                 class="rounded-circle js-photo-preview"
                                 data-url="' . e($url) . '"
                                 style="width:36px;height:36px;object-fit:cover;cursor:pointer;"
                                 alt="Foto">';
                }
                $initial = strtoupper(mb_substr($row->first_name ?? '?', 0, 1));
                return '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white mx-auto"
                             style="width:36px;height:36px;font-size:14px;font-weight:600;">' . e($initial) . '</div>';
            })
            ->addColumn('full_name', function (Employee $row) {
                return e(trim($row->first_name . ' ' . ($row->last_name ?? '')));
            })
            ->filterColumn('full_name', function($query, $keyword) {
                $query->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$keyword}%"]);
            })
            ->orderColumn('full_name', function ($query, $order) {
                $query->orderByRaw("CONCAT(first_name, ' ', last_name) $order");
            })
            ->editColumn('is_active', function (Employee $row) {
                return $row->is_active === 'SI'
                    ? '<span class="badge bg-success">SI</span>'
                    : '<span class="badge bg-danger">NO</span>';
            })
            ->orderColumn('employee_number', 'CAST(employee_number AS BIGINT) $1')
            ->addColumn('actions', function (Employee $row) {
                $photoBtn = '<button class="btn btn-sm btn-outline-info js-photo-btn me-1"
                                     data-id="' . e($row->id) . '"
                                     data-bs-toggle="tooltip"
                                     title="Cargar fotografía">
                                 <i class="ti ti-camera"></i>
                             </button>';

                if ($row->user_id !== null) {
                    $promoteBtn = '<span class="btn btn-sm btn-outline-secondary disabled"
                                        data-bs-toggle="tooltip"
                                        title="Ya tiene usuario asignado">
                                      <i class="ti ti-user-check"></i>
                                  </span>';
                } else {
                    $promoteBtn = '<button class="btn btn-sm btn-outline-primary js-promote-btn"
                                          data-id="' . e($row->id) . '"
                                          data-bs-toggle="tooltip"
                                          title="Crear usuario staff">
                                      <i class="ti ti-user-plus"></i>
                                  </button>';
                }

                return $photoBtn . $promoteBtn;
            })
            ->rawColumns(['photo', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Recibe datos de un empleado desde el script Python y los persiste.
     * POST /api/empleados/recibir
     */
    public function recibir(Request $request): JsonResponse
    {
        $request->validate([
            'Nombre' => 'required|string|max:255',
        ]);

        $data = $request->all();

        $numero  = $this->str($data, 'Numero');
        $empresa = $this->str($data, 'Empresa');
        $rfc     = $this->str($data, 'RFC');

        // Estrategia de búsqueda:
        // 1. Si tiene número y empresa → buscar por ambos (caso normal)
        // 2. Si falta alguno pero tiene RFC → buscar solo por RFC
        // 3. Sin ningún identificador → siempre crear (no se puede deduplicar)
        if ($numero !== null && $empresa !== null) {
            $employee = Employee::where('employee_number', $numero)
                ->where('company', $empresa)
                ->first();
        } elseif ($rfc !== null) {
            $employee = Employee::where('rfc', $rfc)->first();
        } else {
            $employee = null;
        }

        $esNuevo = $employee === null;

        if ($esNuevo) {
            $employee = new Employee([
                'employee_number' => $numero,
                'company'         => $empresa,
            ]);
        }

        // Capturar valores RAW antes de cualquier cambio
        $valoresAnteriores = $esNuevo ? [] : $employee->getRawOriginal();

        ['first_name' => $firstName, 'last_name' => $lastName] =
            $this->parsearNombre($this->str($data, 'Nombre'));

        $rawLider = $this->str($data, 'Lider');
        $liderLimpio = $this->limpiarLider($rawLider);
        $liderNumero = $this->resolverLider($liderLimpio, $rawLider);
        $liderId = $liderNumero ? $this->resolverLiderId($liderNumero) : null;

        $employee->fill([
            'archivo_origen'     => $this->str($data, 'archivo_origen'),
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'department'         => $this->str($data, 'Departamento'),
            'job_title'          => $this->str($data, 'Puesto'),
            'hire_date'          => $this->date($data, 'FechaIngreso'),
            'is_active'          => $this->str($data, 'Activo'),
            'termination_date'   => $this->date($data, 'FechaBaja'),
            'rehire_eligible'    => $this->str($data, 'Recontratar'),
            'termination_reason' => $this->str($data, 'MotivoBaja'),
            'team'               => $this->str($data, 'Equipo'),
            'seniority'          => $this->str($data, 'Antiguedad'),
            'rfc'                => $rfc,
            'imss'               => $this->str($data, 'IMSS'),
            'curp'               => $this->str($data, 'CURP'),
            'gender'             => $this->str($data, 'Genero'),
            'phone'              => $this->str($data, 'Telefono'),
            'address'            => $this->str($data, 'Direccion'),
            'email'              => $this->str($data, 'Correo'),
            'education'          => $this->str($data, 'Estudios'),
            'responsible'        => $this->str($data, 'Responsable'),
            'leader'             => $liderNumero,
            'leader_id'          => $liderId,
            'vacation_balance'   => $this->decimal($data, 'SaldoVacaciones'),
            'savings_fund'       => $this->decimal($data, 'FondoAhorro'),
            'daily_salary'       => $this->decimal($data, 'SalarioDiario'),
            'severance_bonus'    => $this->decimal($data, 'Grat.Separacion'),
            'indemnization'      => $this->decimal($data, 'Indemnizacion'),
            'seniority_premium'  => $this->decimal($data, 'PrimaDeAntig.'),
        ]);

        $employee->save();

        $eventos = 0;

        if (!$esNuevo) {
            $eventos = $this->registrarCambios($valoresAnteriores, $employee);
        }

        return response()->json([
            'success' => true,
            'message' => $esNuevo ? 'Empleado creado' : 'Empleado actualizado',
            'id'      => $employee->id,
            'eventos' => $eventos,
        ], $esNuevo ? 201 : 200);
    }

    // ── Segunda pasada: resolución de líderes pendientes ─────────────────────

    /**
     * Recorre todos los empleados cuyo leader aún es un nombre (no un número)
     * e intenta resolverlo ahora que la tabla está completa.
     *
     * Agrupa por nombre único para hacer una sola consulta por líder distinto
     * y luego actualiza en batch. Ideal para llamar al final del script de importación.
     *
     * POST /api/empleados/resolver-lideres
     */
    public function resolverLideresPendientes(): JsonResponse
    {
        // Obtener nombres únicos pendientes: leader no nulo y no puramente numérico
        $pendientes = Employee::whereNotNull('leader')
            ->whereRaw("leader LIKE '%[^0-9]%'")
            ->distinct()
            ->pluck('leader');

        $resueltos   = 0;
        $sinResolver = 0;

        foreach ($pendientes as $nombre) {
            // Reutilizar resolverLider: el nombre limpio ya no tiene prefijo,
            // así que se pasa como rawLider también (quitarPrefijo lo devuelve igual)
            $numero = $this->resolverLider($nombre, $nombre);

            if ($numero !== $nombre) {
                $liderId = $this->resolverLiderId($numero);
                Employee::where('leader', $nombre)->update(['leader' => $numero, 'leader_id' => $liderId]);
                $resueltos++;
            } else {
                // Intentar poblar leader_id aunque el número ya esté resuelto
                $liderId = $this->resolverLiderId($nombre);
                if ($liderId !== null) {
                    Employee::where('leader', $nombre)->whereNull('leader_id')->update(['leader_id' => $liderId]);
                }
                $sinResolver++;
            }
        }

        return response()->json([
            'success'      => true,
            'resueltos'    => $resueltos,
            'sin_resolver' => $sinResolver,
        ]);
    }

    // ── Lógica de eventos ─────────────────────────────────────────────────────

    /**
     * Campos que NO se comparan para detectar cambios.
     */
    private const CAMPOS_EXCLUIDOS_COMPARACION = [
        'hire_date',
        'savings_fund',
        'seniority_premium',
    ];

    /**
     * Campos de fecha que deben normalizarse a Y-m-dd para comparación.
     */
    private const CAMPOS_FECHA = [
        'hire_date',
        'termination_date',
    ];

    /**
     * Campos decimales que deben normalizarse para comparación.
     */
    private const CAMPOS_DECIMALES = [
        'daily_salary',
        'severance_bonus',
        'indemnization',
        'seniority_premium',
    ];

    /**
     * Campos que se comparan como números enteros.
     */
    private const CAMPOS_ENTEROS = [
        'vacation_balance',
    ];

    /**
     * Compara los valores RAW anteriores contra los guardados
     * e inserta un EmployeeEvent por cada campo que haya cambiado.
     *
     * @return int Número de eventos registrados
     */
    private function registrarCambios(array $antes, Employee $despues): int
    {
        $eventos = [];

        foreach (self::CAMPOS_RASTREADOS as $campo => $etiqueta) {
            // Saltar campos excluidos de la comparación
            if (in_array($campo, self::CAMPOS_EXCLUIDOS_COMPARACION, true)) {
                continue;
            }

            $valorAntes   = $antes[$campo] ?? null;
            $valorDespues = $despues->getRawOriginal($campo);

            // Normalizar según tipo de campo
            if (in_array($campo, self::CAMPOS_FECHA, true)) {
                $valorAntes   = $this->normalizarFecha($valorAntes);
                $valorDespues = $this->normalizarFecha($valorDespues);
            } elseif (in_array($campo, self::CAMPOS_DECIMALES, true)) {
                $valorAntes   = $this->normalizarDecimal($valorAntes);
                $valorDespues = $this->normalizarDecimal($valorDespues);
            } elseif (in_array($campo, self::CAMPOS_ENTEROS, true)) {
                $valorAntes   = $this->normalizarEntero($valorAntes);
                $valorDespues = $this->normalizarEntero($valorDespues);
            } else {
                $valorAntes   = $this->normalizar($valorAntes);
                $valorDespues = $this->normalizar($valorDespues);
            }

            if ($valorAntes === $valorDespues) {
                continue;
            }

            $eventos[] = [
                'employee_id'    => $despues->id,
                'campo'          => $campo,
                'evento'         => "Se actualizó el campo '{$etiqueta}' del empleado",
                'valor_anterior' => $valorAntes,
                'valor_nuevo'    => $valorDespues,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        if (!empty($eventos)) {
            EmployeeEvent::insert($eventos);
        }

        return count($eventos);
    }

    /**
     * Convierte cualquier valor a string normalizado para comparación.
     * Fechas Carbon se estandarizan a Y-m-d; nulls y strings vacíos → null.
     */
    private function normalizar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /**
     * Normaliza fechas a formato Y-m-d para evitar falsos positivos
     * por diferencias de hora o formato (ej: "2024-01-15" vs "2024-01-15 00:00:00").
     */
    private function normalizarFecha(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Carbon instance
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }

        // String con fecha (puede incluir hora)
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            // Intentar parsear y extraer solo la fecha
            try {
                $dt = new \DateTime($value);
                return $dt->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return (string) $value;
    }

    /**
     * Normaliza decimales para evitar falsos positivos por diferencia de precisión.
     * Ej: "1000.50" vs "1000.50000" → ambos se convierten a "1000.50"
     */
    private function normalizarDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);
        if ($str === '' || !is_numeric($str)) {
            return $str;
        }

        // Formatear con 2 decimales para estandarizar
        return number_format((float) $str, 2, '.', '');
    }

    /**
     * Normaliza valores enteros para evitar falsos positivos.
     * Ej: "9" vs "9.0000" → ambos se convierten a "9".
     */
    private function normalizarEntero(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);
        if ($str === '' || !is_numeric($str)) {
            return $str;
        }

        return (string) (int) round((float) $str);
    }

    // ── Helpers de parseo ─────────────────────────────────────────────────────

    /**
     * Divide el nombre del empleado en first_name y last_name.
     *
     * - Con coma ("Apellidos, Nombre(s)"): separación exacta.
     * - Sin coma (ya en orden natural): primer token = first_name, resto = last_name.
     *
     * @return array{first_name: string|null, last_name: string|null}
     */
    private function parsearNombre(?string $valor): array
    {
        if ($valor === null || trim($valor) === '') {
            return ['first_name' => null, 'last_name' => null];
        }

        $valor = trim($valor);

        if (str_contains($valor, ',')) {
            [$apellidos, $nombres] = explode(',', $valor, 2);
            return [
                'first_name' => trim($nombres) ?: null,
                'last_name'  => trim($apellidos) ?: null,
            ];
        }

        // Sin coma: primer token = nombre(s), resto = apellidos
        $partes = preg_split('/\s+/', $valor, 2);
        return [
            'first_name' => $partes[0] ?? null,
            'last_name'  => isset($partes[1]) ? $partes[1] : null,
        ];
    }

    /**
     * Convierte "Apellidos, Nombre" → "Nombre Apellidos".
     * Si no hay coma, devuelve el valor sin cambios.
     */
    private function reordenarNombre(string $nombre): string
    {
        if (!str_contains($nombre, ',')) {
            return $nombre;
        }

        [$apellidos, $nombres] = explode(',', $nombre, 2);
        return trim($nombres) . ' ' . trim($apellidos);
    }

    /**
     * Quita el prefijo tipo "9235 - " o "Jarudo - " al inicio del valor.
     * Reutilizado por limpiarLider y resolverLider.
     */
    private function quitarPrefijo(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return str_contains($valor, ' - ')
            ? trim(substr($valor, strpos($valor, ' - ') + 3))
            : trim($valor);
    }

    /**
     * Limpia el nombre del líder recibido del archivo externo.
     *
     * - Elimina el prefijo de clave (e.g. "9235 - ", "Jarudo - ").
     * - Retorna null para valores sin información útil:
     *   vacío, "-", "no aplica", "desconocido", "vacante", "vacant", "sin jefe".
     * - Reordena "Apellidos, Nombre" → "Nombre Apellidos".
     */
    private function limpiarLider(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $nombre = $this->quitarPrefijo($valor) ?? '';

        $sinLider = ['no aplica', 'desconocido', 'vacante', 'vacant', 'sin jefe', '-', 'null'];

        if ($nombre === '' || in_array(mb_strtolower($nombre), $sinLider, true)) {
            return null;
        }

        return $this->reordenarNombre($nombre);
    }

    /**
     * Intenta resolver el nombre del líder a su número de empleado.
     *
     * Usa el valor crudo para extraer apellidos y nombre por separado
     * y hacer una búsqueda LIKE doble contra first_name / last_name.
     * Solo sustituye si encuentra exactamente un resultado (evita ambigüedad).
     * Si no hay match, devuelve el nombre limpio como fallback.
     *
     * @param string|null $nombreLimpio  Nombre ya limpio y reordenado (fallback)
     * @param string|null $rawLider      Valor original del campo Lider
     */
    private function resolverLider(?string $nombreLimpio, ?string $rawLider): ?string
    {
        if ($nombreLimpio === null || $rawLider === null) {
            return $nombreLimpio;
        }

        // Quitar prefijo para obtener "Apellidos, Nombre" o "Nombre Apellidos"
        $sinPrefijo = $this->quitarPrefijo($rawLider) ?? '';

        if ($sinPrefijo === '') {
            return $nombreLimpio;
        }

        // LIKE con el fragmento disponible (puede estar truncado en el origen)
        $query = Employee::whereNotNull('employee_number');

        if (str_contains($sinPrefijo, ',')) {
            // Formato origen "Apellidos, Nombre" → split exacto, LIKE por separado
            [$apellidos, $nombres] = explode(',', $sinPrefijo, 2);
            $apellidos = trim($apellidos);
            $nombres   = trim($nombres);

            if ($nombres !== '') {
                $query->where('first_name', 'like', $nombres . '%');
            }
            if ($apellidos !== '') {
                $query->where('last_name', 'like', $apellidos . '%');
            }
        } else {
            // Sin coma: el valor ya está en orden natural "Nombre Apellidos".
            // Buscar contra el nombre completo concatenado para evitar el problema
            // de split asimétrico (e.g. "Vicente Alejandro Carril" no parte igual
            // que first_name="Vicente Alejandro" / last_name="Carrillo").
            $query->whereRaw(
                "(first_name + ' ' + ISNULL(last_name, '')) LIKE ?",
                [$sinPrefijo . '%']
            );
        }

        $numeros = $query->pluck('employee_number')->unique()->values();

        // Un solo número → puede aparecer en varios archivos/empresas, es la misma persona
        if ($numeros->count() === 1) {
            return $numeros->first();
        }

        // Varios candidatos con distinto número → desempatar por activo
        // Clonamos la query agregando el filtro is_active = 'SI'
        $numerosActivos = (clone $query)->where('is_active', 'SI')
            ->pluck('employee_number')
            ->unique()
            ->values();

        if ($numerosActivos->count() >= 1) {
            return $numerosActivos->sort()->last();
        }

        return $nombreLimpio;
    }

    /**
     * Dado un número de empleado ya resuelto, devuelve el id del registro.
     * Solo aplica si el valor es puramente numérico (employee_number resuelto).
     */
    private function resolverLiderId(?string $numeroEmpleado): ?int
    {
        if ($numeroEmpleado === null || !ctype_digit($numeroEmpleado)) {
            return null;
        }

        return Employee::where('employee_number', $numeroEmpleado)
            ->where('is_active', 'SI')
            ->value('id')
            ?? Employee::where('employee_number', $numeroEmpleado)->value('id');
    }

    private function str(array $data, string $key): ?string
    {
        $value = trim($data[$key] ?? '');
        return $value !== '' ? $value : null;
    }

    private function date(array $data, string $key): ?string
    {
        $value = trim($data[$key] ?? '');
        if ($value === '') {
            return null;
        }
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);
        return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : null;
    }

    private function decimal(array $data, string $key): ?string
    {
        $value = trim($data[$key] ?? '');
        return is_numeric($value) ? $value : null;
    }
}
