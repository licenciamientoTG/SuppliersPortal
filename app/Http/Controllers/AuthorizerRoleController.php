<?php

namespace App\Http\Controllers;

use App\Models\AuthorizerRole;
use App\Models\DirectPurchaseOrder;
use App\Models\QuotationSummary;
use App\Models\UserAuthorizerRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthorizerRoleController extends Controller
{
    public function index()
    {
        $roles = AuthorizerRole::query()
            ->withCount(['assignments', 'quotationSummaries', 'directPurchaseOrders'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('authorizer_roles.index', compact('roles'));
    }

    public function create()
    {
        $authorizerRole = new AuthorizerRole([
            'is_active' => true,
        ]);

        return view('authorizer_roles.create', compact('authorizerRole'));
    }

    public function store(Request $request)
    {
        $data = $this->validateRole($request);

        AuthorizerRole::create([
            'name' => $data['name'],
            'approval_limit' => $data['approval_limit'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('authorizer-roles.index')
            ->with('success', 'Rol autorizador creado correctamente.');
    }

    public function edit(AuthorizerRole $authorizerRole)
    {
        return view('authorizer_roles.edit', compact('authorizerRole'));
    }

    public function update(Request $request, AuthorizerRole $authorizerRole)
    {
        $data = $this->validateRole($request, $authorizerRole);

        $authorizerRole->update([
            'name' => $data['name'],
            'approval_limit' => $data['approval_limit'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('authorizer-roles.index')
            ->with('success', 'Rol autorizador actualizado correctamente.');
    }

    public function destroy(Request $request, AuthorizerRole $authorizerRole)
    {
        $assignmentsCount = $authorizerRole->assignments()->count();
        $summariesCount = $authorizerRole->quotationSummaries()->count();
        $directOrdersCount = $authorizerRole->directPurchaseOrders()->count();
        $forceDelete = (bool) $request->boolean('force_delete');

        if ($assignmentsCount > 0 && ! $forceDelete) {
            return redirect()->route('authorizer-roles.index')
                ->with('warning', "El rol '{$authorizerRole->name}' tiene {$assignmentsCount} usuario(s) ligado(s). Confirma el borrado para continuar.");
        }

        DB::transaction(function () use ($authorizerRole) {
            UserAuthorizerRole::query()
                ->where('authorizer_role_id', $authorizerRole->id)
                ->delete();

            QuotationSummary::query()
                ->where('authorizer_role_id', $authorizerRole->id)
                ->update(['authorizer_role_id' => null]);

            DirectPurchaseOrder::query()
                ->where('authorizer_role_id', $authorizerRole->id)
                ->update(['authorizer_role_id' => null]);

            $authorizerRole->delete();
        });

        $message = 'Rol autorizador eliminado correctamente.';

        if ($assignmentsCount > 0 || $summariesCount > 0 || $directOrdersCount > 0) {
            $message .= " Se limpiaron {$assignmentsCount} asignacion(es) de usuario, {$summariesCount} referencia(s) en cotizaciones y {$directOrdersCount} referencia(s) en ordenes directas.";
        }

        return redirect()->route('authorizer-roles.index')->with('success', $message);
    }

    private function validateRole(Request $request, ?AuthorizerRole $authorizerRole = null): array
    {
        if (is_string($request->input('approval_limit'))) {
            $request->merge([
                'approval_limit' => str_replace([',', '$', ' '], '', $request->input('approval_limit')),
            ]);
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('authorizer_roles', 'name')->ignore($authorizerRole?->id),
            ],
            'approval_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isGeneralDirectorRole = mb_strtolower(trim((string) $data['name'])) === mb_strtolower('Dirección General');

        if (($data['approval_limit'] ?? null) === null && ! $isGeneralDirectorRole) {
            throw ValidationException::withMessages([
                'approval_limit' => 'Solo Dirección General puede quedar sin límite de autorización.',
            ]);
        }

        return $data;
    }
}
