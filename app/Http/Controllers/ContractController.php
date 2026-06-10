<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\Contract;
use App\Models\Company;
use App\Models\ProductService;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Services\ContractImportService;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ContractController extends Controller
{
    public function index()
    {
        return view('contracts.index');
    }

    public function datatable(Request $request)
    {
        if (! $request->ajax()) {
            abort(403);
        }

        $query = Contract::with(['supplier', 'company', 'creator'])
            ->select('contracts.*');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('folio_col', fn($c) => '<span class="fw-bold">' . e($c->folio) . '</span>')
            ->addColumn('supplier_name', fn($c) => e($c->supplier->company_name ?? '—'))
            ->addColumn('company_name', fn($c) => e($c->company->name ?? '—'))
            ->addColumn('start_date_col', fn($c) => $c->start_date->format('d/m/Y'))
            ->addColumn('end_date_col', fn($c) => $c->end_date->format('d/m/Y'))
            ->addColumn('status_col', function ($c) {
                $icon = match($c->effective_status) {
                    'active'    => 'file-check',
                    'expired'   => 'clock-x',
                    default     => 'file-x',
                };
                return '<span class="badge bg-' . $c->effective_status_badge . '">'
                    . '<i class="ti ti-' . $icon . ' me-1"></i>'
                    . e($c->effective_status_label) . '</span>';
            })
            ->addColumn('actions', function ($c) {
                $show = route('contracts.show', $c->id);
                $btns = '<a href="' . $show . '" class="btn btn-sm btn-outline-primary me-1"><i class="ti ti-eye"></i></a>';
                if ($c->effective_status === 'active') {
                    $edit = route('contracts.edit', $c->id);
                    $btns .= '<a href="' . $edit . '" class="btn btn-sm btn-outline-secondary"><i class="ti ti-edit"></i></a>';
                }
                return $btns;
            })
            ->rawColumns(['folio_col', 'status_col', 'actions'])
            ->make(true);
    }

    public function create()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('status', 'activo')->orderBy('company_name')->get();
        $productServices = ProductService::where('is_active', true)->orderBy('short_name')->get();
        return view('contracts.create', compact('companies', 'suppliers', 'productServices'));
    }

    public function store(StoreContractRequest $request)
    {
        DB::transaction(function () use ($request) {
            $contract = Contract::create([
                'folio'           => Contract::nextFolio(),
                'supplier_id'     => $request->supplier_id,
                'company_id'      => $request->company_id,
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'contract_amount' => $request->contract_amount ?? 0,
                'status'          => 'active',
                'created_by'      => Auth::id(),
                'updated_by'      => Auth::id(),
            ]);

            foreach ($request->products as $p) {
                $contract->products()->create([
                    'product_service_id' => $p['product_service_id'],
                    'unit_price'         => $p['unit_price'],
                    'currency_code'      => $p['currency_code'],
                    'unit_of_measure'    => $p['unit_of_measure'],
                    'notes'              => $p['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('contracts.index')
            ->with('success', 'Contrato creado correctamente.');
    }

    public function show(Contract $contract)
    {
        $contract->load(['supplier', 'company', 'products.product', 'creator', 'cancelledByUser']);
        $history = Activity::forSubject($contract)->latest()->get();
        $purchases = RequisitionItem::with(['requisition'])
            ->where('contract_id', $contract->id)
            ->latest()
            ->paginate(20);

        return view('contracts.show', compact('contract', 'history', 'purchases'));
    }

    public function edit(Contract $contract)
    {
        abort_if($contract->effective_status !== 'active', 403, 'Solo se pueden editar contratos activos.');
        $contract->load('products');
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('status', 'activo')->orderBy('company_name')->get();
        $productServices = ProductService::where('is_active', true)->orderBy('short_name')->get();
        return view('contracts.edit', compact('contract', 'companies', 'suppliers', 'productServices'));
    }

    public function update(UpdateContractRequest $request, Contract $contract)
    {
        abort_if($contract->effective_status !== 'active', 403);

        DB::transaction(function () use ($request, $contract) {
            $contract->update([
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'contract_amount' => $request->contract_amount ?? 0,
                'updated_by'      => Auth::id(),
            ]);

            $incomingIds = collect($request->products)->pluck('product_service_id')->filter()->toArray();
            $contract->products()->whereNotIn('product_service_id', $incomingIds)->delete();

            foreach ($request->products as $p) {
                $contract->products()->updateOrCreate(
                    ['product_service_id' => $p['product_service_id']],
                    [
                        'unit_price'      => $p['unit_price'],
                        'currency_code'   => $p['currency_code'],
                        'unit_of_measure' => $p['unit_of_measure'],
                        'notes'           => $p['notes'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contrato actualizado.');
    }

    public function cancel(Request $request, Contract $contract)
    {
        $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        abort_if($contract->effective_status !== 'active', 422, 'El contrato ya no está activo.');

        $contract->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_by'        => Auth::id(),
            'cancelled_at'        => now(),
            'updated_by'          => Auth::id(),
        ]);

        activity('contracts')
            ->causedBy(Auth::user())
            ->performedOn($contract)
            ->event('cancelled')
            ->withProperties([
                'old'    => ['status' => 'active'],
                'new'    => ['status' => 'cancelled'],
                'reason' => $request->cancellation_reason,
            ])
            ->log('Contrato cancelado');

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Contrato cancelado.');
    }

    // ── Carga masiva ─────────────────────────────────────────────────────

    public function importForm()
    {
        return view('contracts.import');
    }

    public function downloadTemplate()
    {
        $csvContent = "empresa_code,supplier_rfc,start_date,end_date,contract_amount,product_code,unit_price,currency\n";
        $csvContent .= "TG001,AAA010101AAA,2026-01-01,2026-12-31,100000,PROD-001,250.5000,MXN\n";

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="plantilla_contratos.csv"',
        ]);
    }

    public function importPreview(Request $request, ContractImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:5120'],
        ]);

        $result = $service->preview($request->file('file'));

        // Fix #2 — store validated rows in session instead of a forgeable hidden field
        session(['contract_import_valid' => $result['valid']]);

        return view('contracts.import-preview', compact('result'));
    }

    public function importConfirm(Request $request, ContractImportService $service)
    {
        // Fix #2 — retrieve from session, not from user-supplied POST data
        $validRows = session('contract_import_valid', []);

        if (empty($validRows)) {
            return redirect()->route('contracts.importForm')
                ->with('error', 'No hay filas válidas en sesión. Vuelva a cargar el archivo.');
        }

        session()->forget('contract_import_valid');

        $created = $service->confirm($validRows);

        return redirect()->route('contracts.index')
            ->with('success', "Importación completa. {$created} contratos creados.");
    }

    public function requisitionCreate()
    {
        return view('contracts.requisition-create');
    }
}
