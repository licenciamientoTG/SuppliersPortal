<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCostCenterRequest;
use App\Models\Category;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\User;
use App\Services\CostCenterDistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CostCenterController extends Controller
{
    public function index(): View
    {
        return view('cost_centers.index');
    }

    public function datatable(Request $request)
    {
        $query = CostCenter::query()
            ->with([
                'category',
                'company',
                'responsible',
                'createdBy',
            ])
            ->notDeleted();

        return DataTables::of($query)
            ->editColumn('code', function ($row) {
                return '<strong>' . e($row->code) . '</strong>';
            })
            ->editColumn('name', function ($row) {
                return e($row->name);
            })
            ->addColumn('category_name', function ($row) {
                return e($row->category?->name ?? '-');
            })
            ->addColumn('company_name', function ($row) {
                return e($row->company?->name ?? '-');
            })
            ->addColumn('purchase_type_label', function ($row) {
                return e($row->purchase_type?->value ?? $row->purchase_type ?? '-');
            })
            ->addColumn('budget_type_label', function ($row) {
                return $row->budget_type === 'ANNUAL'
                    ? '<span class="badge bg-info">Presupuesto Anual</span>'
                    : '<span class="badge bg-warning">Consumo Libre</span>';
            })
            ->editColumn('status', function ($row) {
                return $row->status === 'ACTIVO'
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';
            })
            ->addColumn('responsible_name', function ($row) {
                return e($row->responsible?->name ?? '-');
            })
            ->addColumn('created_by_name', function ($row) {
                return e($row->createdBy?->name ?? '-');
            })
            ->addColumn('actions', function ($row) {
                $editUrl = route('cost-centers.edit', $row->id);
                $deleteUrl = route('cost-centers.destroy', $row->id);

                return '<div class="d-flex justify-content-end gap-1">'
                    . '<a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary" title="Editar"><i class="ti ti-pencil"></i></a>'
                    . '<form action="' . $deleteUrl . '" method="POST" class="d-inline js-delete-form">'
                    . csrf_field() . method_field('DELETE')
                    . '<button type="button" class="btn btn-sm btn-outline-danger js-delete-btn" data-entity="' . e($row->name) . '" title="Eliminar"><i class="ti ti-trash"></i></button>'
                    . '</form>'
                    . '</div>';
            })
            ->rawColumns(['code', 'budget_type_label', 'status', 'actions'])
            ->make(true);
    }

    public function create(): View
    {
        $costCenter = new CostCenter([
            'status' => 'ACTIVO',
            'budget_type' => 'ANNUAL',
            'cost_center_type' => 'STANDARD',
        ]);

        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $companies = Company::orderBy('name')
            ->get(['id', 'name']);

        $users = $this->eligibleResponsibleUsers();
        $destinationCenters = CostCenter::active()->where('cost_center_type', 'STANDARD')->orderBy('name')->get(['id', 'code', 'name', 'company_id']);

        return view('cost_centers.create', compact(
            'costCenter',
            'categories',
            'companies',
            'users', 'destinationCenters'
        ));
    }

    public function store(SaveCostCenterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = null;

        DB::transaction(function () use ($data, $request) {
            unset($data['destinations']);
            $center = CostCenter::create($data);
            app(CostCenterDistributionService::class)->validateConfiguration($center, $request->input('destinations', []));
            $center->distributionTargets()->createMany($request->input('destinations', []));
        });

        return redirect()
            ->route('cost-centers.index')
            ->with('success', 'Centro de costo creado correctamente.');
    }

    public function edit(CostCenter $cost_center): View
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $companies = Company::orderBy('name')
            ->get(['id', 'name']);

        $users = $this->eligibleResponsibleUsers();
        $destinationCenters = CostCenter::active()->where('cost_center_type', 'STANDARD')->whereKeyNot($cost_center->id)->orderBy('name')->get(['id', 'code', 'name', 'company_id']);
        $cost_center->load('distributionTargets');

        return view('cost_centers.edit', [
            'cost_center' => $cost_center,
            'costCenter' => $cost_center,
            'categories' => $categories,
            'companies' => $companies,
            'users' => $users,
            'destinationCenters' => $destinationCenters,
        ]);
    }

    public function update(SaveCostCenterRequest $request, CostCenter $cost_center): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        DB::transaction(function () use ($cost_center, $data, $request) {
            unset($data['destinations']);
            $cost_center->update($data);
            app(CostCenterDistributionService::class)->validateConfiguration($cost_center, $request->input('destinations', []));
            $cost_center->distributionTargets()->delete();
            $cost_center->distributionTargets()->createMany($request->input('destinations', []));
        });

        return redirect()
            ->route('cost-centers.index')
            ->with('success', 'Centro de costo actualizado correctamente.');
    }

    public function destroy(CostCenter $cost_center): RedirectResponse
    {
        $cost_center->update(['deleted_by' => auth()->id()]);
        $cost_center->delete();

        return redirect()
            ->route('cost-centers.index')
            ->with('success', 'Centro de costo eliminado correctamente.');
    }

    public function byCompany(Request $request, Company $company)
    {
        $purchaseType = $request->query('purchase_type');

        $centers = $request->user()
            ->costCenters()
            ->where('cost_centers.company_id', $company->id)
            ->where('cost_centers.status', 'ACTIVO')
            ->whereNull('cost_centers.deleted_at')
            ->wherePivot('is_active', true)
            ->when($purchaseType, fn ($query) => $query->where('cost_centers.purchase_type', $purchaseType))
            ->orderBy('cost_centers.name')
            ->get(['cost_centers.id', 'cost_centers.name', 'cost_centers.code', 'cost_centers.purchase_type']);

        return response()->json($centers);
    }

    private function eligibleResponsibleUsers()
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'authorizer'))
            ->whereHas('roles', fn ($query) => $query->where('name', 'department_head'))
            ->orderBy('name')->get(['id', 'name']);
    }
}
