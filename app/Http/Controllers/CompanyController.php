<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CompanyController extends Controller
{
    /**
     * Campos del domicilio fiscal que deben capturarse juntos (el número interior es opcional).
     */
    private const REQUIRED_FISCAL_ADDRESS_FIELDS = [
        'fiscal_street',
        'fiscal_exterior_number',
        'fiscal_neighborhood',
        'fiscal_municipality',
        'fiscal_state',
        'fiscal_postal_code',
    ];

    /**
     * Mostrar la vista principal de empresas.
     */
    public function index()
    {
        return view('companies.index');
    }

    /**
     * Endpoint AJAX para DataTable.
     */
    public function datatable(Request $request)
    {
        $user = $request->user();

        // Empresas visibles = todas las empresas
        $query = Company::all();

        return DataTables::of($query)
            ->addColumn('tax_regime_label', fn ($row) => $row->taxRegimeLabel() ?? '—')
            ->addColumn('fiscal_address', fn ($row) => e(implode(' · ', $row->fiscalAddressLines())) ?: '<span class="text-muted">Sin capturar</span>')
            ->addColumn('actions', function ($row) {
                return view('companies.partials.actions', compact('row'))->render();
            })
            ->editColumn('is_active', fn ($row) => $row->is_active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>')
            ->rawColumns(['fiscal_address', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Mostrar formulario de creación.
     */
    public function create()
    {
        // Instancia vacía con algunos defaults razonables
        $company = new Company([
            'locale' => app()->getLocale() ?? 'es_MX',
            'timezone' => config('app.timezone', 'America/Mexico_City'),
            'currency_code' => 'MXN',
            'is_active' => true,
        ]);
        $taxRegimes = Company::taxRegimeOptions();

        return view('companies.partials.form', compact('company', 'taxRegimes'));
    }

    /**
     * Guardar una nueva empresa.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), [], $this->attributes());

        // Normalizar a boolean real
        $validated['is_active'] = $request->boolean('is_active');

        Company::create($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Company $company)
    {
        $taxRegimes = Company::taxRegimeOptions();

        // Devuelve solo el HTML del formulario (partial)
        return view('companies.partials.form', compact('company', 'taxRegimes'));
    }

    /**
     * Actualizar una empresa existente.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate($this->rules($company), [], $this->attributes());

        $validated['is_active'] = $request->boolean('is_active');

        $company->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Eliminar empresa.
     */
    public function destroy(Company $company)
    {
        $company->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reglas compartidas de alta y edición. El domicilio fiscal es opcional,
     * pero si se captura cualquier dato deben completarse todos los obligatorios.
     */
    private function rules(?Company $company = null): array
    {
        $fiscalAddressRequiredWith = function (string $field): string {
            $others = array_diff([...self::REQUIRED_FISCAL_ADDRESS_FIELDS, 'fiscal_interior_number'], [$field]);

            return 'required_with:'.implode(',', $others);
        };

        return [
            'code' => 'required|string|max:20|unique:companies,code'.($company ? ','.$company->id : ''),
            'name' => 'required|string|max:150',
            'legal_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:13',
            'tax_regime' => ['nullable', 'string', Rule::in(array_keys(Company::taxRegimeOptions()))],
            'fiscal_street' => ['nullable', 'string', 'max:150', $fiscalAddressRequiredWith('fiscal_street')],
            'fiscal_exterior_number' => ['nullable', 'string', 'max:20', $fiscalAddressRequiredWith('fiscal_exterior_number')],
            'fiscal_interior_number' => ['nullable', 'string', 'max:20'],
            'fiscal_neighborhood' => ['nullable', 'string', 'max:100', $fiscalAddressRequiredWith('fiscal_neighborhood')],
            'fiscal_municipality' => ['nullable', 'string', 'max:100', $fiscalAddressRequiredWith('fiscal_municipality')],
            'fiscal_state' => ['nullable', 'string', 'max:50', $fiscalAddressRequiredWith('fiscal_state')],
            'fiscal_postal_code' => ['nullable', 'digits:5', $fiscalAddressRequiredWith('fiscal_postal_code')],
            'locale' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'currency_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'domain' => 'nullable|string|max:150',
            'website' => 'nullable|string|max:150',
            'logo_path' => 'nullable|string|max:255',
            'is_active' => 'required|boolean', // con el hidden siempre viene
        ];
    }

    /** Nombres legibles para los mensajes de validación del domicilio fiscal. */
    private function attributes(): array
    {
        return [
            'tax_regime' => 'régimen fiscal',
            'fiscal_street' => 'calle',
            'fiscal_exterior_number' => 'número exterior',
            'fiscal_interior_number' => 'número interior',
            'fiscal_neighborhood' => 'colonia',
            'fiscal_municipality' => 'municipio o alcaldía',
            'fiscal_state' => 'estado',
            'fiscal_postal_code' => 'código postal',
        ];
    }
}
