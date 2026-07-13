<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class DepartmentController extends Controller
{
    public function index()
    {
        return view('departments.index');
    }

    public function datatable()
    {
        $query = Department::query();

        return DataTables::of($query)
            ->editColumn('abbreviated', function ($row) {
                return '<span class="badge bg-secondary">'.e($row->abbreviated).'</span>';
            })
            ->editColumn('is_active', function ($row) {
                return $row->is_active
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-danger">Inactivo</span>';
            })
            ->addColumn('actions', function ($row) {
                $editUrl = route('departments.edit', $row->id);

                return '
                <div class="d-flex justify-content-end gap-1">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-edit"></i>
                    </a>
                </div>';
            })
            ->rawColumns(['abbreviated', 'is_active', 'actions'])
            ->make(true);
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:departments,name'],
            'abbreviated' => ['required', 'string', 'max:10', 'unique:departments,abbreviated'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // ✅ Garantizar valores correctos
        $data['is_active'] = (bool) $request->input('is_active');
        $data['created_by'] = Auth::id();

        Department::create($data);

        return redirect()
            ->route('departments.index')
            ->with('success', 'Departamento creado correctamente.');
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'abbreviated' => [
                'required',
                'string',
                'max:10',
                Rule::unique('departments', 'abbreviated')->ignore($department->id),
            ],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // ✅ Garantizar que se guarde el valor 0 o 1 según el switch
        $data['is_active'] = (bool) $request->input('is_active');

        $department->update($data);

        return redirect()
            ->route('departments.index')
            ->with('success', 'Departamento actualizado correctamente.');
    }

    public function destroy(Department $department)
    {
        $department->update(['is_active' => false]);

        return redirect()->route('departments.index')->with('success', 'Departamento desactivado correctamente.');
    }
}
