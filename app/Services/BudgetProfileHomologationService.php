<?php

namespace App\Services;

use App\Models\BudgetProfile;
use App\Models\Employee;
use App\Models\EmployeePositionBudgetProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class BudgetProfileHomologationService
{
    private const EXCLUDED_TITLES = [
        'mensajero',
        'escoltas',
        'velador',
        'vigilante',
        'chofer operador',
        'auxiliar de limpieza',
        'chofer ejecutivo',
        'operador',
        'oficial de servicio al cliente',
    ];

    private const EXCLUDED_DEPARTMENTS = [
        'misiones aqua',
    ];

    private const PROFILE_RULES = [
        'station_management' => ['gerente de estacion', 'subgerente de estacion'],
        'finance_accounting' => ['contab', 'finanza', 'tesorer', 'credito', 'cobranza', 'cortes', 'nomina', 'cuentas por pagar', 'factur', 'ingresos', 'presupuesto', 'administracion'],
        'purchasing_supply' => ['compra', 'abasto', 'logistica', 'importacion', 'inventario'],
        'technology' => ['sistema', 'software', 'soporte', 'ti ', 'programador'],
        'maintenance_security' => ['mantenimiento', 'mantto', 'limpieza', 'seguridad', 'vigilante', 'velador', 'sasisopa'],
        'commercial_marketing' => ['venta', 'comercial', 'marketing', 'mercadotecnia', 'mkt', 'cliente', 'contenido', 'community', 'relaciones publicas'],
        'human_capital' => ['capital humano', 'recursos humanos', 'reclut', 'capacitacion', 'compensaciones', 'relaciones laborales', 'psicologa'],
        'projects_expansion' => ['proyecto', 'expansion', 'construccion', 'ingenieria', 'arquitectura', 'bienes raices', 'project'],
        'audit_legal_compliance' => ['auditor', 'juridico', 'normatividad', 'cumplimiento'],
        'executive_management' => ['director', 'administrador general', 'gerente general', 'gerente de unidad'],
        'operations' => ['operador', 'operaciones', 'zona', 'estacionamiento', 'combustible'],
        'administrative_support' => ['auxiliar', 'asistente', 'recepcionista', 'mensajero', 'chofer', 'capturista', 'encargado'],
    ];

    public function seedProfiles(): void
    {
        foreach ($this->profileDefinitions() as $key => $definition) {
            BudgetProfile::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    public function syncEmployeePositions(): void
    {
        $this->seedProfiles();

        Employee::query()
            ->where('is_active', 'SI')
            ->whereNotNull('job_title')
            ->select(['job_title', 'department'])
            ->orderBy('job_title')
            ->get()
            ->reject(fn ($employee) => in_array(
                EmployeePositionBudgetProfile::normalizeJobTitle($employee->department),
                self::EXCLUDED_DEPARTMENTS,
                true
            ))
            ->groupBy('job_title')
            ->each(function ($employees, $jobTitle) {
                $normalized = EmployeePositionBudgetProfile::normalizeJobTitle($jobTitle);
                $isExcluded = in_array($normalized, self::EXCLUDED_TITLES, true);
                $profile = $isExcluded ? null : $this->profileForNormalizedTitle($normalized);

                EmployeePositionBudgetProfile::query()->updateOrCreate(
                    ['raw_job_title' => $jobTitle],
                    [
                        'normalized_job_title' => $normalized,
                        'budget_profile_id' => $profile?->id,
                        'is_excluded' => $isExcluded,
                        'employees_count' => $employees->count(),
                    ]
                );
            });

        Employee::query()
            ->with('user')
            ->where('is_active', 'SI')
            ->whereNotNull('user_id')
            ->whereNotNull('job_title')
            ->get()
            ->each(function (Employee $employee) {
                if ($employee->user) {
                    $this->assignBudgetProfileFromEmployee($employee->user, $employee);
                }
            });
    }

    public function assignBudgetProfileFromEmployee(User $user, ?Employee $employee = null): void
    {
        $employee ??= $user->employee;
        $normalized = EmployeePositionBudgetProfile::normalizeJobTitle($employee?->job_title ?? $user->job_title);
        $department = EmployeePositionBudgetProfile::normalizeJobTitle($employee?->department);

        if (
            ! $normalized
            || in_array($normalized, self::EXCLUDED_TITLES, true)
            || in_array($department, self::EXCLUDED_DEPARTMENTS, true)
        ) {
            $user->forceFill(['budget_profile_id' => null])->save();

            return;
        }

        $profile = EmployeePositionBudgetProfile::query()
            ->where('normalized_job_title', $normalized)
            ->where('is_excluded', false)
            ->with('budgetProfile')
            ->first()
            ?->budgetProfile
            ?? $this->profileForNormalizedTitle($normalized);

        $user->forceFill(['budget_profile_id' => $profile?->id])->save();
    }

    private function profileForNormalizedTitle(?string $normalized): ?BudgetProfile
    {
        if (! $normalized) {
            return null;
        }

        foreach (self::PROFILE_RULES as $profileKey => $terms) {
            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    return BudgetProfile::query()->where('key', $profileKey)->first();
                }
            }
        }

        return BudgetProfile::query()->where('key', 'needs_review')->first();
    }

    private function profileDefinitions(): Collection
    {
        return collect([
            'station_management' => ['name' => 'Gerencia de estaciones', 'description' => 'Gerentes y subgerentes de estaciones.'],
            'operations' => ['name' => 'Operaciones', 'description' => 'Operacion, zonas, combustible y estacionamiento.'],
            'finance_accounting' => ['name' => 'Finanzas y contabilidad', 'description' => 'Finanzas, contabilidad, tesoreria, credito, cobranza y presupuesto.'],
            'purchasing_supply' => ['name' => 'Compras y abasto', 'description' => 'Compras, abasto, logistica e inventarios.'],
            'technology' => ['name' => 'Tecnologia', 'description' => 'Sistemas, soporte, TI y desarrollo de software.'],
            'maintenance_security' => ['name' => 'Mantenimiento y seguridad', 'description' => 'Mantenimiento, limpieza, seguridad y SASISOPA.'],
            'commercial_marketing' => ['name' => 'Comercial y marketing', 'description' => 'Ventas, comercial, mercadotecnia y atencion a clientes.'],
            'human_capital' => ['name' => 'Capital humano', 'description' => 'Recursos humanos, reclutamiento, capacitacion y compensaciones.'],
            'projects_expansion' => ['name' => 'Proyectos y expansion', 'description' => 'Proyectos, expansion, construccion e ingenieria.'],
            'audit_legal_compliance' => ['name' => 'Auditoria, legal y cumplimiento', 'description' => 'Auditoria, juridico, normatividad y cumplimiento.'],
            'executive_management' => ['name' => 'Direccion y gerencia ejecutiva', 'description' => 'Direccion y administracion ejecutiva.'],
            'administrative_support' => ['name' => 'Soporte administrativo', 'description' => 'Auxiliares, asistentes y soporte administrativo.'],
            'needs_review' => ['name' => 'Pendiente de revision', 'description' => 'Puestos ambiguos que requieren homologacion manual.'],
        ]);
    }
}
