<?php

namespace App\Http\Controllers;

use App\Models\ReceivingLocation;
use App\Models\Company;
use App\Http\Requests\SaveReceivingLocationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Controlador para gestionar ubicaciones de recepción
 * 
 * Proporciona funcionalidades CRUD para ReceivingLocation con integración:
 * - Request unificado (ReceivingLocationRequest) para validación
 * - Policy (ReceivingLocationPolicy) para autorización
 * - Yajra DataTables para el listado con acciones
 * - Respuestas JSON para operaciones AJAX
 */
class ReceivingLocationController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
    /**
     * Constructor
     */
    public function __construct()
    {
        // La autorización se maneja mediante ReceivingLocationPolicy
    }

    /**
     * Display a listing of the resource.
     * 
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        // Autorizar vista
        $this->authorize('viewAny', ReceivingLocation::class);
        
        return view('receiving-locations.index', [
            'title' => 'Ubicaciones de Recepción',
            'types' => ReceivingLocation::TYPES, // Para filtros en la vista
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * 
     * @return \Illuminate\View\View
     */
    public function create(): View
    {
        // Autorizar creación
        $this->authorize('create', ReceivingLocation::class);
        
        return view('receiving-locations.create', [
            'title' => 'Nueva Ubicación de Recepción',
            'types' => ReceivingLocation::TYPES,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'location' => new ReceivingLocation(), // Modelo vacío para el form
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param  \App\Http\Requests\ReceivingLocationRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(SaveReceivingLocationRequest $request): RedirectResponse
    {
        // Autorizar creación
        $this->authorize('create', ReceivingLocation::class);
        
        try {
            // Obtener datos validados con valores por defecto
            $validated = $request->getValidatedWithDefaults();
            
            // Crear la ubicación
            $location = ReceivingLocation::create($validated);
            
            // Log de auditoría
            Log::info('Ubicación de recepción creada', [
                'user_id' => auth()->id(),
                'location_id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
            ]);
            
            return redirect()
                ->route('receiving-locations.index')
                ->with('success', "Ubicación '{$location->name}' creada correctamente.");
                
        } catch (\Exception $e) {
            Log::error('Error al crear ubicación', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error al crear la ubicación: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     * 
     * @param  \App\Models\ReceivingLocation  $receivingLocation
     * @return \Illuminate\View\View
     */
    public function show(ReceivingLocation $receivingLocation): View
    {
        // Autorizar vista
        $this->authorize('view', $receivingLocation);
        
        // Cargar relaciones para la vista de detalle
        
        return view('receiving-locations.show', [
            'title' => "Ubicación: {$receivingLocation->name}",
            'location' => $receivingLocation,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * 
     * @param  \App\Models\ReceivingLocation  $receivingLocation
     * @return \Illuminate\View\View
     */
    public function edit(ReceivingLocation $receivingLocation): View
    {
        // Autorizar edición
        $this->authorize('update', $receivingLocation);
        
        return view('receiving-locations.edit', [
            'title' => "Editar Ubicación: {$receivingLocation->name}",
            'types' => ReceivingLocation::TYPES,
            'location' => $receivingLocation,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * 
     * @param  \App\Http\Requests\ReceivingLocationRequest  $request
     * @param  \App\Models\ReceivingLocation  $receivingLocation
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(SaveReceivingLocationRequest $request, ReceivingLocation $receivingLocation): RedirectResponse
    {
        // Autorizar actualización
        $this->authorize('update', $receivingLocation);
        
        try {
            // Obtener datos validados
            $validated = $request->getValidatedWithDefaults();
            
            // Guardar datos anteriores para log
            $oldData = $receivingLocation->toArray();
            
            // Actualizar la ubicación
            $receivingLocation->update($validated);
            
            // Log de auditoría
            Log::info('Ubicación de recepción actualizada', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
                'changes' => $receivingLocation->getChanges(),
                'old_data' => $oldData,
            ]);
            
            return redirect()
                ->route('receiving-locations.index')
                ->with('success', "Ubicación '{$receivingLocation->name}' actualizada correctamente.");
                
        } catch (\Exception $e) {
            Log::error('Error al actualizar ubicación', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error al actualizar la ubicación: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param  \App\Models\ReceivingLocation  $receivingLocation
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroy(ReceivingLocation $receivingLocation): JsonResponse|RedirectResponse
    {
        // Autorizar eliminación
        $this->authorize('delete', $receivingLocation);
        
        try {
            $name = $receivingLocation->name;
            
            // Eliminar la ubicación
            $receivingLocation->delete();
            
            // Log de auditoría
            Log::info('Ubicación de recepción eliminada', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
                'code' => $receivingLocation->code,
                'name' => $name,
            ]);
            
            // Si es petición AJAX, responder JSON
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Ubicación '{$name}' eliminada correctamente.",
                ]);
            }
            
            return redirect()
                ->route('receiving-locations.index')
                ->with('success', "Ubicación '{$name}' eliminada correctamente.");
                
        } catch (\Exception $e) {
            Log::error('Error al eliminar ubicación', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
                'error' => $e->getMessage(),
            ]);
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar la ubicación: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()
                ->back()
                ->with('error', 'Error al eliminar la ubicación: ' . $e->getMessage());
        }
    }

    /**
     * Get data for DataTables.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Yajra\DataTables\DataTables|\Illuminate\Http\JsonResponse
     */
    public function getData(Request $request): JsonResponse
    {
        // Autorizar vista
        $this->authorize('viewAny', ReceivingLocation::class);
        
        try {
            $query = ReceivingLocation::query()->with('company');
            
            return DataTables::of($query)
                ->addColumn('action', function($location) {
                    return $this->getActionButtons($location);
                })
                ->editColumn('type', function($location) {
                    return $this->getTypeBadge($location);
                })
                ->addColumn('company', function($location) {
                    return $location->company
                        ? $location->company->code.' - '.$location->company->name
                        : '-';
                })
                ->editColumn('is_active', function($location) {
                    return $location->status_badge;
                })
                ->editColumn('portal_blocked', function($location) {
                    return $location->portal_badge;
                })
                ->editColumn('created_at', function($location) {
                    return $location->created_at ? $location->created_at->format('d/m/Y H:i') : '-';
                })
                ->filterColumn('name', function($query, $keyword) {
                    $query->where('name', 'like', "%{$keyword}%");
                })
                ->filterColumn('code', function($query, $keyword) {
                    $query->where('code', 'like', "%{$keyword}%");
                })
                ->filterColumn('city', function($query, $keyword) {
                    $query->where('city', 'like', "%{$keyword}%");
                })
                ->orderColumn('name', 'name $1')
                ->orderColumn('code', 'code $1')
                ->orderColumn('created_at', 'created_at $1')
                ->rawColumns(['action', 'type', 'is_active', 'portal_blocked'])
                ->make(true);
                
        } catch (\Exception $e) {
            Log::error('Error en DataTable de ubicaciones', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Error al cargar los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bloquear portal de la ubicación.
     * 
     * @param  \App\Models\ReceivingLocation  $receivingLocation
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockPortal(ReceivingLocation $receivingLocation): JsonResponse
    {
        // Autorizar bloqueo
        $this->authorize('blockPortal', $receivingLocation);
        
        try {
            $receivingLocation->blockPortal();
            
            Log::info('Portal bloqueado para ubicación', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Portal bloqueado correctamente.',
                'portal_badge' => $receivingLocation->portal_badge,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al bloquear portal', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al bloquear portal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desbloquear portal de la ubicación.
     * 
     * @param  \App\Models\ReceivingLocation  $receivingLocation
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblockPortal(ReceivingLocation $receivingLocation): JsonResponse
    {
        // Autorizar desbloqueo
        $this->authorize('unblockPortal', $receivingLocation);
        
        try {
            $receivingLocation->unblockPortal();
            
            Log::info('Portal desbloqueado para ubicación', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Portal desbloqueado correctamente.',
                'portal_badge' => $receivingLocation->portal_badge,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al desbloquear portal', [
                'user_id' => auth()->id(),
                'location_id' => $receivingLocation->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al desbloquear portal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar botones de acción para cada fila.
     * 
     * @param  \App\Models\ReceivingLocation  $location
     * @return string
     */
    private function getActionButtons(ReceivingLocation $location): string
    {
        $buttons = '<div class="d-flex justify-content-end gap-1">';
        
        // Botón Ver - disponible para todos con permiso
        if (auth()->user()->can('view', $location)) {
            $buttons .= '<a href="' . route('receiving-locations.show', $location) . '"
                           class="btn btn-sm btn-outline-secondary"
                           title="Ver detalles">
                           <i class="ti ti-eye"></i>
                        </a>';
        }

        // Botón Editar - solo para buyer y superadmin
        if (auth()->user()->can('update', $location)) {
            $buttons .= '<a href="' . route('receiving-locations.edit', $location) . '"
                           class="btn btn-sm btn-outline-primary"
                           title="Editar">
                           <i class="ti ti-edit"></i>
                        </a>';
        }

        // Botón Bloquear/Desbloquear Portal - para buyer y accounting
        if (auth()->user()->can('blockPortal', $location)) {
            if ($location->portal_blocked) {
                $buttons .= '<button type="button"
                                   class="btn btn-sm btn-outline-success btn-unblock-portal"
                                   data-id="' . $location->id . '"
                                   title="Desbloquear Portal">
                                   <i class="ti ti-lock-open"></i>
                            </button>';
            } else {
                $buttons .= '<button type="button"
                                   class="btn btn-sm btn-outline-danger btn-block-portal"
                                   data-id="' . $location->id . '"
                                   title="Bloquear Portal">
                                   <i class="ti ti-lock"></i>
                            </button>';
            }
        }

        // Botón Eliminar - solo para buyer y superadmin
        if (auth()->user()->can('delete', $location)) {
            $buttons .= '<button type="button"
                           class="btn btn-sm btn-outline-danger btn-delete"
                           data-id="' . $location->id . '"
                           data-name="' . $location->name . '"
                           title="Eliminar">
                           <i class="ti ti-trash"></i>
                        </button>';
        }
        
        $buttons .= '</div>';

        
        return $buttons;
    }

    /**
     * Generar badge para el tipo de ubicación.
     * 
     * @param  \App\Models\ReceivingLocation  $location
     * @return string
     */
    private function getTypeBadge(ReceivingLocation $location): string
    {
        $colors = [
            'service_station' => 'primary',
            'corporate' => 'success',
            'warehouse' => 'info',
            'other' => 'secondary',
        ];
        
        $color = $colors[$location->type] ?? 'secondary';
        $typeName = $location->type_name;
        
        return "<span class='badge bg-{$color}'>{$typeName}</span>";
    }
}
