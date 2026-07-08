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

            // Permisos del sistema (nuevo esquema granular)
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'productos.eliminar',
            'productos.administrar',
            'requisiciones.ver',
            'requisiciones.crear',
            'requisiciones.autorizar',
            'catalogo_cuentas.ver',
            'catalogo_cuentas.crear',
            'catalogo_cuentas.editar',
            'catalogo_cuentas.eliminar',
            'departamentos.administrar',
            'puestos.administrar',
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

            // Buyer — módulos: quotations, purchase_orders, receptions, products_services, payments_billing, document_review
            $buyerRole->syncPermissions([
                // Proveedores
                'view_suppliers',
                'create_suppliers',
                'edit_suppliers',
                'approve_suppliers',
                'delete_suppliers',
                // Órdenes de compra
                'view_orders',
                'create_orders',
                'edit_orders',
                'delete_orders',
                'approve_orders',
                'reject_orders',
                // Cotizaciones
                'view_quotes',
                'create_quotes',
                'edit_quotes',
                'approve_quotes',
                // Facturas y pagos
                'view_invoices',
                'approve_invoices',
                'reject_invoices',
                'process_payments',
                // Catálogo (products_services)
                'manage_products',
                'manage_categories',
                'manage_services',
                'approve_products',
                // Recepciones
                'view_receptions',
                'confirm_receptions',
                // Revisión documental
                'view_documents',
                'review_documents',
                // Reportes
                'view_purchase_reports',
                'view_supplier_reports',
                // Perfil
                'edit_own_profile',
            ]);

            // Contabilidad — módulos: budget_control, payments_billing, communicator
            $accountingRole->syncPermissions([
                // Proveedores (solo lectura)
                'view_suppliers',
                // Órdenes (solo lectura)
                'view_orders',
                // Facturas y pagos
                'view_invoices',
                'create_invoices',
                'edit_invoices',
                'approve_invoices',
                'reject_invoices',
                'process_payments',
                // Presupuesto
                'view_budget',
                'manage_budget',
                // Reportes
                'view_purchase_reports',
                'view_accounting_reports',
                'view_supplier_reports',
                // Perfil
                'edit_own_profile',
            ]);

            // Proveedor — módulos: quotations, purchase_orders, receptions, supplier_*
            $supplierRole->syncPermissions([
                // Órdenes propias
                'view_own_orders',
                // Cotizaciones
                'view_quotes',
                'create_quotes',
                'edit_quotes',
                // Facturas propias
                'create_invoices',
                'edit_invoices',
                // Recepciones
                'view_receptions',
                // Perfil
                'edit_own_profile',
            ]);

            // Autorizador — módulos: quotations, purchase_orders, budget_control
            $authorizerRole->syncPermissions([
                // Proveedores
                'view_suppliers',
                'approve_suppliers',
                // Órdenes
                'view_orders',
                'approve_orders',
                'reject_orders',
                // Facturas
                'view_invoices',
                'approve_invoices',
                'reject_invoices',
                // Cotizaciones
                'view_quotes',
                'approve_quotes',
                // Presupuesto
                'view_budget',
                // Reportes
                'view_purchase_reports',
                'view_accounting_reports',
                // Perfil
                'edit_own_profile',
            ]);

            // Staff — módulos: requisitions (solo)
            $staffRole->syncPermissions([
                'view_requisitions',
                'create_requisitions',
                'view_own_orders',
                'edit_own_profile',
            ]);

            // Receptor — módulos: receptions (solo)
            $receiverRole->syncPermissions([
                'view_orders',
                'view_own_orders',
                'view_receptions',
                'confirm_receptions',
                'edit_own_profile',
            ]);

            // Director General — módulos: quotations, purchase_orders, budget_control
            $generalDirectorRole->syncPermissions([
                // Proveedores (solo lectura y aprobación)
                'view_suppliers',
                'approve_suppliers',
                // Órdenes
                'view_orders',
                'approve_orders',
                'reject_orders',
                // Facturas
                'view_invoices',
                'approve_invoices',
                'reject_invoices',
                // Cotizaciones
                'view_quotes',
                'approve_quotes',
                // Presupuesto
                'view_budget',
                'manage_budget',
                // Aprobación de catálogo (ejecutivo)
                'approve_products',
                // Reportes ejecutivos
                'view_system_reports',
                'view_purchase_reports',
                'view_accounting_reports',
                'view_supplier_reports',
                // Perfil
                'edit_own_profile',
            ]);

            // Admin de Catálogo — módulos: products_services (solo)
            $catalogAdminRole->syncPermissions([
                'manage_products',
                'manage_categories',
                'manage_services',
                'approve_products',
                'productos.ver',
                'productos.crear',
                'productos.editar',
                'productos.eliminar',
                'productos.administrar',
                'catalogo_cuentas.ver',
                'catalogo_cuentas.crear',
                'catalogo_cuentas.editar',
                'catalogo_cuentas.eliminar',
                'edit_own_profile',
            ]);

            // Jefe de Departamento — módulos: budget_control, payments_billing
            $departmentHeadRole->syncPermissions([
                // Órdenes e facturas (solo lectura)
                'view_orders',
                'view_invoices',
                // Pagos
                'process_payments',
                // Presupuesto
                'view_budget',
                'manage_budget',
                // Reportes
                'view_purchase_reports',
                'view_accounting_reports',
                // Perfil
                'edit_own_profile',
            ]);

            $staffRole->givePermissionTo([
                'productos.ver',
                'requisiciones.ver',
                'requisiciones.crear',
            ]);

            $buyerRole->givePermissionTo([
                'productos.ver',
                'productos.crear',
                'productos.editar',
                'requisiciones.ver',
                'requisiciones.autorizar',
            ]);

            $accountingRole->givePermissionTo([
                'catalogo_cuentas.ver',
                'catalogo_cuentas.editar',
            ]);
        });

        // Refresca caché de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
