<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RolesCatalogController extends Controller
{
    /**
     * Orden de presentación de los roles en el catálogo.
     */
    private const ROLE_ORDER = [
        'superadmin',
        'general_director',
        'buyer',
        'accounting',
        'authorizer',
        'catalog_admin',
        'department_head',
        'staff',
        'receiver',
        'supplier',
    ];

    /**
     * Metadatos visuales por rol (etiqueta, icono Tabler, color hex).
     */
    private const ROLE_META = [
        'superadmin'       => ['label' => 'Super Administrador',    'icon' => 'ti-shield-check',    'color' => '#1a4b96', 'desc' => 'Acceso total al sistema sin restricciones.'],
        'general_director' => ['label' => 'Director General',       'icon' => 'ti-crown',            'color' => '#d97706', 'desc' => 'Visibilidad completa y aprobaciones ejecutivas de alto nivel.'],
        'buyer'            => ['label' => 'Comprador',              'icon' => 'ti-shopping-cart',    'color' => '#059669', 'desc' => 'Gestión del ciclo completo de compras y cotizaciones.'],
        'accounting'       => ['label' => 'Contabilidad',           'icon' => 'ti-calculator',       'color' => '#7c3aed', 'desc' => 'Gestión de facturas, pagos y reportes contables.'],
        'authorizer'       => ['label' => 'Autorizador',            'icon' => 'ti-stamp',            'color' => '#0284c7', 'desc' => 'Aprobación y rechazo de solicitudes del sistema.'],
        'catalog_admin'    => ['label' => 'Admin. de Catálogo',     'icon' => 'ti-package',          'color' => '#0891b2', 'desc' => 'Administración de productos, servicios y categorías.'],
        'department_head'  => ['label' => 'Jefe de Departamento',   'icon' => 'ti-building',         'color' => '#475569', 'desc' => 'Gestión financiera y de órdenes a nivel departamental.'],
        'staff'            => ['label' => 'Staff',                  'icon' => 'ti-briefcase',        'color' => '#6b7280', 'desc' => 'Acceso de consulta a compras, proveedores y cotizaciones.'],
        'receiver'         => ['label' => 'Receptor',               'icon' => 'ti-clipboard-check',  'color' => '#ea580c', 'desc' => 'Recepción y seguimiento de órdenes de compra.'],
        'supplier'         => ['label' => 'Proveedor',              'icon' => 'ti-truck',            'color' => '#dc2626', 'desc' => 'Acceso al portal externo para proveedores registrados.'],
    ];

    /**
     * Agrupación de permisos por categoría (para la vista).
     */
    public const PERMISSION_CATEGORIES = [
        'Usuarios y Sistema' => [
            'manage_users', 'manage_roles', 'manage_system_settings', 'view_system_reports',
        ],
        'Proveedores' => [
            'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers', 'approve_suppliers',
        ],
        'Órdenes de Compra' => [
            'view_orders', 'create_orders', 'edit_orders', 'delete_orders', 'approve_orders', 'reject_orders',
        ],
        'Facturas y Pagos' => [
            'view_invoices', 'create_invoices', 'edit_invoices', 'approve_invoices', 'reject_invoices', 'process_payments',
        ],
        'Cotizaciones' => [
            'view_quotes', 'create_quotes', 'edit_quotes', 'approve_quotes',
        ],
        'Reportes' => [
            'view_purchase_reports', 'view_accounting_reports', 'view_supplier_reports',
        ],
        'Catálogo' => [
            'manage_products', 'manage_categories', 'manage_services', 'approve_products',
        ],
        'Personal' => [
            'edit_own_profile', 'view_own_orders',
        ],
    ];

    /**
     * Etiquetas legibles por permiso.
     */
    public const PERMISSION_LABELS = [
        'manage_users'            => 'Gestionar usuarios',
        'manage_roles'            => 'Gestionar roles',
        'manage_system_settings'  => 'Configuración del sistema',
        'view_system_reports'     => 'Reportes del sistema',
        'view_suppliers'          => 'Ver proveedores',
        'create_suppliers'        => 'Registrar proveedores',
        'edit_suppliers'          => 'Editar proveedores',
        'delete_suppliers'        => 'Eliminar proveedores',
        'approve_suppliers'       => 'Aprobar proveedores',
        'view_orders'             => 'Ver órdenes',
        'create_orders'           => 'Crear órdenes',
        'edit_orders'             => 'Editar órdenes',
        'delete_orders'           => 'Eliminar órdenes',
        'approve_orders'          => 'Aprobar órdenes',
        'reject_orders'           => 'Rechazar órdenes',
        'view_invoices'           => 'Ver facturas',
        'create_invoices'         => 'Crear facturas',
        'edit_invoices'           => 'Editar facturas',
        'approve_invoices'        => 'Aprobar facturas',
        'reject_invoices'         => 'Rechazar facturas',
        'process_payments'        => 'Procesar pagos',
        'view_quotes'             => 'Ver cotizaciones',
        'create_quotes'           => 'Crear cotizaciones',
        'edit_quotes'             => 'Editar cotizaciones',
        'approve_quotes'          => 'Aprobar cotizaciones',
        'view_purchase_reports'   => 'Reportes de compras',
        'view_accounting_reports' => 'Reportes contables',
        'view_supplier_reports'   => 'Reportes de proveedores',
        'manage_products'         => 'Gestionar productos',
        'manage_categories'       => 'Gestionar categorías',
        'manage_services'         => 'Gestionar servicios',
        'approve_products'        => 'Aprobar productos',
        'edit_own_profile'        => 'Editar perfil propio',
        'view_own_orders'         => 'Ver mis órdenes',
    ];

    public function index(): View
    {
        $order = self::ROLE_ORDER;

        $roles = Role::withCount('users')
            ->with('permissions')
            ->get()
            ->sortBy(fn ($role) => ($idx = array_search($role->name, $order)) !== false ? $idx : 999)
            ->values();

        return view('roles.catalog', [
            'roles'      => $roles,
            'roleMeta'   => self::ROLE_META,
            'categories' => self::PERMISSION_CATEGORIES,
            'permLabels' => self::PERMISSION_LABELS,
        ]);
    }
}
