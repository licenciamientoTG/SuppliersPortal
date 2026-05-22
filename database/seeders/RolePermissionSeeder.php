<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Asegura que Spatie no use caché vieja
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Gestión de usuarios y sistema
            'manage_users',
            'manage_roles',
            'view_system_reports',
            'manage_system_settings',

            // Gestión de proveedores
            'view_suppliers',
            'create_suppliers',
            'edit_suppliers',
            'delete_suppliers',
            'approve_suppliers',

            // Gestión de órdenes de compra
            'view_orders',
            'create_orders',
            'edit_orders',
            'delete_orders',
            'approve_orders',
            'reject_orders',

            // Gestión de facturas
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'approve_invoices',
            'reject_invoices',
            'process_payments',

            // Gestión de cotizaciones
            'view_quotes',
            'create_quotes',
            'edit_quotes',
            'approve_quotes',

            // Reportes
            'view_purchase_reports',
            'view_accounting_reports',
            'view_supplier_reports',

            // Perfil propio
            'edit_own_profile',
            'view_own_orders',

            // Catálogo de productos y servicios
            'manage_products',
            'manage_categories',
            'manage_services',
            'approve_products',

            // Requisiciones
            'view_requisitions',
            'create_requisitions',

            // Recepciones
            'view_receptions',
            'confirm_receptions',

            // Control presupuestal
            'view_budget',
            'manage_budget',

            // Revisión documental
            'view_documents',
            'review_documents',
        ];

        DB::transaction(function () use ($permissions) {
            // Crear permisos (idempotente)
            foreach ($permissions as $name) {
                Permission::findOrCreate($name, 'web');
            }

            // Crear roles (idempotente)
            $superAdminRole = Role::findOrCreate('superadmin', 'web');
            $buyerRole = Role::findOrCreate('buyer', 'web');
            $accountingRole = Role::findOrCreate('accounting', 'web');
            $supplierRole = Role::findOrCreate('supplier', 'web');
            $authorizerRole = Role::findOrCreate('authorizer', 'web');
            $staffRole = Role::findOrCreate('staff', 'web');
            $receiverRole = Role::findOrCreate('receiver', 'web');
            $generalDirectorRole = Role::findOrCreate('general_director', 'web');
            $catalogAdminRole = Role::findOrCreate('catalog_admin', 'web');
            $departmentHeadRole = Role::findOrCreate('department_head', 'web');

            // Asignaciones
            $superAdminRole->syncPermissions(Permission::all());

            $buyerRole->syncPermissions([
                'view_suppliers',
                'create_suppliers',
                'edit_suppliers',
                'view_orders',
                'create_orders',
                'edit_orders',
                'view_quotes',
                'create_quotes',
                'edit_quotes',
                'view_purchase_reports',
                'edit_own_profile',
            ]);

            $accountingRole->syncPermissions([
                'view_suppliers',
                'view_orders',
                'view_invoices',
                'create_invoices',
                'edit_invoices',
                'process_payments',
                'view_accounting_reports',
                'edit_own_profile',
            ]);

            $supplierRole->syncPermissions([
                'view_own_orders',
                'create_quotes',
                'edit_quotes',
                'create_invoices',
                'edit_invoices',
                'edit_own_profile',
            ]);

            $authorizerRole->syncPermissions([
                'view_suppliers',
                'view_orders',
                'view_invoices',
                'view_quotes',
                'approve_suppliers',
                'approve_orders',
                'reject_orders',
                'approve_invoices',
                'reject_invoices',
                'approve_quotes',
                'view_purchase_reports',
                'view_accounting_reports',
                'edit_own_profile',
            ]);

            $staffRole->syncPermissions([
                'view_suppliers',
                'view_orders',
                'view_invoices',
                'view_quotes',
                'edit_own_profile',
                'view_own_orders',
            ]);

            $receiverRole->syncPermissions([
                'view_orders',
                'edit_own_profile',
                'view_own_orders',
            ]);

            // NUEVAS ASIGNACIONES DE PERMISOS

            // Director General - Acceso completo a visualización y aprobaciones
            $generalDirectorRole->syncPermissions([
                // Visualización completa
                'view_suppliers',
                'view_orders',
                'view_invoices',
                'view_quotes',

                // Aprobaciones de alto nivel
                'approve_suppliers',
                'approve_orders',
                'reject_orders',
                'approve_invoices',
                'reject_invoices',
                'approve_quotes',
                'approve_products',

                // Reportes ejecutivos
                'view_system_reports',
                'view_purchase_reports',
                'view_accounting_reports',
                'view_supplier_reports',

                // Gestión básica
                'edit_own_profile',
                'manage_products',
                'manage_categories',
                'manage_services',
            ]);

            // Administrador de Catálogo - Enfoque en productos/servicios
            $catalogAdminRole->syncPermissions([
                // Gestión completa de catálogo
                'manage_products',
                'manage_categories',
                'manage_services',
                'approve_products',

                // Visualización relacionada
                'view_suppliers',
                'view_orders',
                'view_quotes',

                // Perfil propio
                'edit_own_profile',
            ]);
            $departmentHeadRole->syncPermissions([
                'view_orders',
                'view_invoices',
                'create_invoices',
                'edit_invoices',
                'process_payments',
                'view_purchase_reports',
                'view_accounting_reports',
                'edit_own_profile',
            ]);
        });

        // Refresca caché de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
