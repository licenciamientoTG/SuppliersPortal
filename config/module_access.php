<?php

return [
    'role_aliases' => [
        'super_admin' => 'superadmin',
        'accountant' => 'accounting',
    ],

    'modules' => [
        'dashboard' => [
            'roles' => ['staff', 'buyer', 'supplier', 'receiver', 'authorizer', 'superadmin', 'general_director', 'catalog_admin', 'accounting', 'department_head'],
        ],
        'reports' => [
            'roles' => ['report_viewer'],
        ],
        'requisitions' => [
            'roles' => ['staff'],
        ],
        'quotations' => [
            'roles' => ['buyer', 'supplier', 'authorizer', 'superadmin', 'general_director'],
        ],
        'purchase_orders' => [
            'roles' => ['staff', 'buyer', 'supplier', 'authorizer', 'superadmin', 'general_director'],
        ],
        'receptions' => [
            'roles' => ['buyer', 'supplier', 'receiver', 'superadmin'],
        ],
        'products_services' => [
            'roles' => ['buyer', 'superadmin', 'catalog_admin'],
        ],
        'budget_control' => [
            'roles' => ['authorizer', 'superadmin', 'general_director', 'accounting', 'department_head'],
        ],
        'budget_profiles' => [
            'roles' => ['superadmin', 'catalog_admin', 'department_head'],
        ],
        'payments_billing' => [
            'roles' => ['buyer', 'superadmin', 'accounting', 'department_head'],
        ],
        'document_review' => [
            'roles' => ['buyer', 'superadmin'],
        ],
        'supplier_document_catalog' => [
            'roles' => ['buyer', 'superadmin'],
        ],
        'communicator' => [
            'roles' => ['superadmin', 'accounting'],
        ],
        'staff_users' => [
            'roles' => ['superadmin'],
        ],
        'employees' => [
            'roles' => ['superadmin'],
        ],
        'catalogs_config' => [
            'roles' => ['superadmin'],
        ],
        'reported_incidents' => [
            'roles' => ['superadmin'],
        ],
        'monitoring_alerts' => [
            'roles' => ['buyer', 'accounting', 'general_director'],
        ],
        'monitoring_operations' => [
            'roles' => ['buyer', 'general_director'],
        ],
        'monitoring_budget' => [
            'roles' => ['accounting', 'general_director'],
        ],
        'monitoring_suppliers' => [
            'roles' => ['buyer', 'general_director'],
        ],
        'monitoring_security' => [
            'roles' => ['superadmin'],
        ],
        'supplier_documents' => [
            'roles' => ['supplier'],
        ],
        'supplier_communicator' => [
            'roles' => ['supplier'],
        ],
        'supplier_billing' => [
            'roles' => ['supplier'],
        ],
    ],
];
