<?php

use App\Http\Controllers\AccountCatalogController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnualBudgetController;
use App\Http\Controllers\ApprovalLevelController;
use App\Http\Controllers\ApprovalDelegationController;
use App\Http\Controllers\AdminApprovalDelegationController;
use App\Http\Controllers\AuthorizationInboxController;
use App\Http\Controllers\AuthorizerRoleController;
use App\Http\Controllers\BudgetMonthlyDistributionController;
use App\Http\Controllers\BudgetMovementController;
use App\Http\Controllers\BudgetProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CatSupplierController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CostCenterImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DirectPurchaseOrderController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseCedulaCatalogController;
use App\Http\Controllers\FinanceInvoiceController;
use App\Http\Controllers\FinancialProvisionController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LockScreenController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePasswordController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuotationApprovalController;
use App\Http\Controllers\QuotationPlannerController;
use App\Http\Controllers\ReceivingLocationController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\RequisitionWorkflowController;
use App\Http\Controllers\RfqComparisonController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\RfqInboxController;
use App\Http\Controllers\RolesCatalogController;
use App\Http\Controllers\SatEfos69bController;
use App\Http\Controllers\SatRetencionController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\SupplierBankController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierDeliveryController;
use App\Http\Controllers\SupplierDocumentController;
use App\Http\Controllers\SupplierDocumentFileController;
use App\Http\Controllers\SupplierDocumentTypeController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\SupplierSirocController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\Tools\CfdiGeneratorController;
use App\Http\Controllers\UserController;
use App\Models\Requisition;
use Illuminate\Support\Facades\Route;

// ============================================================================
//  Serve storage files via PHP when symlink to network path won't work (local dev)
// ============================================================================
Route::get('/storage/{path}', function (string $path) {
    if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

    return response(\Illuminate\Support\Facades\Storage::disk('public')->get($path), 200)
        ->header('Content-Type', $mime);
})->where('path', '.*');

// ============================================================================
//  Default entry point
// ============================================================================
Route::view('/', 'auth.login')->middleware('guest:web,supplier');

// ============================================================================
//  Lock Screen (solo requiere autenticación, no lock)
// ============================================================================
Route::middleware(['auth:web'])->group(function () {
    Route::post('/lock', [LockScreenController::class, 'lock'])->name('lockscreen.lock');
    Route::get('/lock', [LockScreenController::class, 'show'])->name('lockscreen.show');
    Route::post('/unlock', [LockScreenController::class, 'unlock'])->name('lockscreen.unlock');

});

Route::middleware(['auth:web,supplier'])->group(function () {
    Route::get('/supplier-documents/{document}/file', [SupplierDocumentFileController::class, 'show'])
        ->name('supplier-documents.file');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/{notification}/open', [NotificationController::class, 'open'])->name('open');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });
});

// ============================================================================
//  Panel protegido (auth + lock)
// ============================================================================
Route::middleware(['auth', 'lock'])->group(function () {

    // ------------------------------------------------------------------------
    //  Dashboard
    // ------------------------------------------------------------------------
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('module.access:dashboard')
        ->name('dashboard');

    // ------------------------------------------------------------------------
    //  Profile & Account
    // ------------------------------------------------------------------------
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        Route::post('/change-password', [ProfilePasswordController::class, 'update'])->name('password.update');
    });

    // ------------------------------------------------------------------------
    //  Incidents
    // ------------------------------------------------------------------------
    Route::middleware('module.access:reported_incidents')->prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index'])->name('index');
        Route::post('/', [IncidentController::class, 'store'])->name('store');
        Route::delete('/{incident}', [IncidentController::class, 'destroy'])->name('destroy');
    });

    // ========================================================================
    //  User Management
    // ========================================================================
    Route::middleware('module.access:staff_users')->prefix('users')->name('users.')->group(function () {
        // Staff
        Route::get('/', [UserController::class, 'index'])->name('staff.index');
        Route::get('/datatable', [UserController::class, 'datatable'])->name('datatable');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        // Staff (rutas con parámetros)
        Route::get('/{user}', [UserController::class, 'show'])->name('staff.show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::get('/{user}/roles/edit', [UserController::class, 'editRoles'])->name('roles.edit');
        Route::patch('/{user}/roles', [UserController::class, 'updateRoles'])->name('roles.update');

        // Companies (asignación por usuario)
        Route::get('/{user}/companies/edit', [UserController::class, 'editCompanies'])->name('companies.edit');
        Route::patch('/{user}/companies', [UserController::class, 'updateCompanies'])->name('companies.update');

        // Cost Centers (asignación por usuario)
        Route::get('/{user}/cost-centers/edit', [UserController::class, 'editCostCenters'])->name('cost-centers.edit');
        Route::patch('/{user}/cost-centers', [UserController::class, 'updateCostCenters'])->name('cost-centers.update');

        // Supplier relación directa
    });

    // ========================================================================
    //  Admin: Announcements & Document Review
    // ========================================================================
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('module.access:communicator')->prefix('announcements')->name('announcements.')->group(function () {
            Route::get('/', [AnnouncementController::class, 'adminIndex'])->name('index');
            Route::get('/datatable', [AnnouncementController::class, 'adminDatatable'])->name('datatable');
            Route::get('/create', [AnnouncementController::class, 'create'])->name('create');
            Route::post('/', [AnnouncementController::class, 'store'])->name('store');
            Route::get('/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('edit');
            Route::put('/{announcement}', [AnnouncementController::class, 'update'])->name('update');
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('module.access:document_review')->prefix('review')->name('review.')->group(function () {
            Route::get('/', [DocumentReviewController::class, 'index'])->name('index');
            Route::get('/queue', [DocumentReviewController::class, 'queue'])->name('queue');
            Route::get('/suppliers', [DocumentReviewController::class, 'suppliers'])->name('suppliers');
            Route::get('/suppliers/{supplier}', [DocumentReviewController::class, 'showSupplier'])->name('suppliers.show');
            Route::post('/documents/{document}/revalidate', [DocumentReviewController::class, 'revalidate'])->name('documents.revalidate');
            Route::post('/documents/{document}/accept', [DocumentReviewController::class, 'accept'])->name('documents.accept');
            Route::post('/documents/{document}/reject', [DocumentReviewController::class, 'reject'])->name('documents.reject');
        });

        Route::middleware('module.access:document_review')->prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'index'])->name('index');
            Route::get('/datatable', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'datatable'])->name('datatable');
            Route::get('/{supplier}/edit', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'edit'])->name('edit');
            Route::put('/{supplier}', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'update'])->name('update');
            Route::patch('/{supplier}/toggle', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'toggle'])->name('toggle');
            Route::delete('/{supplier}', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'destroy'])->name('destroy');
            Route::post('/{supplier}/approve', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'approve'])->name('approve');
            Route::post('/{supplier}/reject', [\App\Http\Controllers\Admin\SupplierAdminController::class, 'reject'])->name('reject');
        });
    });

    // ========================================================================
    //  Supplier Documents (rutas globales)
    // ========================================================================
    Route::middleware('module.access:document_review')->prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [SupplierDocumentController::class, 'index'])->name('suppliers.index');
        Route::post('/{supplier}', [SupplierDocumentController::class, 'store'])->name('suppliers.store');
        Route::delete('/suppliers/{supplier}/documents/{document}', [SupplierDocumentController::class, 'destroy'])->name('suppliers.destroy');
        Route::post('/review/feedback', [SupplierDocumentController::class, 'feedback'])->name('suppliers.feedback');
    });

    Route::middleware('module.access:supplier_document_catalog')->resource('supplier-document-types', SupplierDocumentTypeController::class)
        ->except(['show', 'destroy']);

    // ========================================================================
    //  Supplier Banking, REPSE & SIROC
    // ========================================================================
    Route::middleware('module.access:document_review')->prefix('suppliers/{supplier}')->name('suppliers.')->group(function () {
        Route::patch('/bank', [SupplierBankController::class, 'update'])->name('bank.update');
        Route::delete('/bank', [SupplierBankController::class, 'destroy'])->name('bank.destroy');
        Route::patch('/repse', [SupplierBankController::class, 'updateRepse'])->name('repse.update');

        Route::prefix('sirocs')->name('sirocs.')->group(function () {
            Route::get('/', [SupplierSirocController::class, 'index'])->name('index');
            Route::get('/create', [SupplierSirocController::class, 'create'])->name('create');
            Route::post('/', [SupplierSirocController::class, 'store'])->name('store');
            Route::get('/{siroc}', [SupplierSirocController::class, 'show'])->name('show');
            Route::get('/{siroc}/edit', [SupplierSirocController::class, 'edit'])->name('edit');
            Route::put('/{siroc}', [SupplierSirocController::class, 'update'])->name('update');
            Route::delete('/{siroc}', [SupplierSirocController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('module.access:document_review')->get('/sirocs', [SupplierSirocController::class, 'adminIndex'])->name('sirocs.index');

    // ========================================================================
    //  SAT & Catalog Management
    // ========================================================================
    Route::middleware('module.access:document_review')->prefix('sat-efos-69b')->name('sat_efos_69b.')->group(function () {
        Route::get('/', [SatEfos69bController::class, 'index'])->name('index');
        Route::get('/data', [SatEfos69bController::class, 'data'])->name('data');
        Route::post('/sync', [SatEfos69bController::class, 'sync'])->name('sync');
        Route::get('/sync/{jobId}', [SatEfos69bController::class, 'syncStatus'])->name('sync.status');
    });

    Route::middleware('module.access:document_review')->prefix('cat-suppliers')->name('cat-suppliers.')->group(function () {
        Route::get('/', [CatSupplierController::class, 'index'])->name('index');
        Route::get('/datatable', [CatSupplierController::class, 'datatable'])->name('datatable');
        Route::get('/{catSupplier}/edit', [CatSupplierController::class, 'edit'])->name('edit');
        Route::put('/{catSupplier}', [CatSupplierController::class, 'update'])->name('update');
    });

    // ========================================================================
    //  Catalogs (Route::resource)
    // ========================================================================
    Route::middleware('module.access:catalogs_config')->group(function () {
        Route::get('companies/datatable', [CompanyController::class, 'datatable'])->name('companies.datatable');
        Route::resource('companies', CompanyController::class)->except(['show']);

        Route::get('stations/datatable', [StationController::class, 'datatable'])->name('stations.datatable');
        Route::resource('stations', StationController::class);
        Route::post('stations/{station}/toggle-active', [StationController::class, 'toggleActive'])->name('stations.toggle-active');
        Route::post('stations/{station}/link-company', [StationController::class, 'linkCompany'])->name('stations.link-company');

        Route::get('taxes/datatable', [TaxController::class, 'datatable'])->name('taxes.datatable');
        Route::resource('taxes', TaxController::class)->except(['show']);

        Route::get('departments/datatable', [DepartmentController::class, 'datatable'])->name('departments.datatable');
        Route::resource('departments', DepartmentController::class)->except(['show']);
    });

    Route::middleware('module.access:budget_control')->group(function () {
        Route::get('categories/datatable', [CategoryController::class, 'datatable'])->name('categories.datatable');
        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::get('cost-centers/datatable', [CostCenterController::class, 'datatable'])->name('cost-centers.datatable');
        Route::get('cost-centers/api/companies/{company}/cost-centers', [CostCenterController::class, 'byCompany'])->name('cost-centers.api.by-company');
        Route::prefix('cost-centers/import')->name('cost-centers.import.')->group(function () {
            Route::get('/template', [CostCenterImportController::class, 'downloadTemplate'])->name('template');
            Route::post('/preview', [CostCenterImportController::class, 'preview'])->name('preview');
            Route::get('/preview', [CostCenterImportController::class, 'showPreview'])->name('preview.show');
            Route::post('/confirm', [CostCenterImportController::class, 'confirm'])->name('confirm');
        });
        Route::resource('cost-centers', CostCenterController::class)->except(['show'])->parameters(['cost-centers' => 'cost_center']);

        Route::prefix('expense-cedulas')->name('expense-cedulas.')->group(function () {
            Route::get('/', [ExpenseCedulaCatalogController::class, 'index'])->name('index');
            Route::get('/categories', [ExpenseCedulaCatalogController::class, 'categoriesData'])->name('categories.data');
            Route::post('/categories', [ExpenseCedulaCatalogController::class, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{expenseCategory}', [ExpenseCedulaCatalogController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/categories/{expenseCategory}', [ExpenseCedulaCatalogController::class, 'destroyCategory'])->name('categories.destroy');
            Route::get('/categories/{expenseCategory}/cedulas', [ExpenseCedulaCatalogController::class, 'cedulasData'])->name('cedulas.data');
            Route::post('/cedulas', [ExpenseCedulaCatalogController::class, 'storeCedula'])->name('cedulas.store');
            Route::put('/cedulas/{budgetCedula}', [ExpenseCedulaCatalogController::class, 'updateCedula'])->name('cedulas.update');
            Route::delete('/cedulas/{budgetCedula}', [ExpenseCedulaCatalogController::class, 'destroyCedula'])->name('cedulas.destroy');
        });

        Route::middleware('can:catalogo_cuentas.ver')
            ->prefix('accounts')
            ->group(function () {
                Route::get('/', [AccountCatalogController::class, 'index'])->name('accounts.index');
                Route::post('/sync', [AccountCatalogController::class, 'sync'])
                    ->middleware('can:catalogo_cuentas.editar')
                    ->name('accounts.sync');
                Route::put('/{account}', [AccountCatalogController::class, 'updateAccount'])
                    ->middleware('can:catalogo_cuentas.editar')
                    ->name('accounts.update');
            });

        Route::put('/subaccounts/{subaccount}', [AccountCatalogController::class, 'updateSubaccount'])
            ->middleware('can:catalogo_cuentas.editar')
            ->name('subaccounts.update');

        Route::middleware('can:catalogo_cuentas.editar')
            ->prefix('budget-profiles')
            ->name('budget-profiles.')
            ->controller(BudgetProfileController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/departments', 'storeDepartment')->name('departments.store');
                Route::put('/departments/{department}', 'updateDepartment')->name('departments.update');
                Route::post('/', 'storeProfile')->name('store');
                Route::put('/{budgetProfile}', 'updateProfile')->name('update');
            });
    });

    Route::middleware('module.access:employees')->group(function () {
        Route::get('employees/datatable', [EmployeeController::class, 'datatable'])->name('employees.datatable');
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}/promote-form', [EmployeeController::class, 'promoteForm'])->name('employees.promote-form');
        Route::post('employees/{employee}/promote', [EmployeeController::class, 'promote'])->name('employees.promote');
        Route::get('employees/{employee}/photo-form', [EmployeeController::class, 'photoForm'])->name('employees.photo-form');
        Route::post('employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto'])->name('employees.upload-photo');
    });

    // ========================================================================
    //  Annual Budgets
    // ========================================================================
    Route::middleware('module.access:budget_control')->prefix('annual_budgets')->name('annual_budgets.')->group(function () {
        Route::get('/', [AnnualBudgetController::class, 'index'])->name('index');
        Route::get('/datatable', [AnnualBudgetController::class, 'datatable'])->name('datatable');
        Route::get('/create', [AnnualBudgetController::class, 'create'])->name('create');
        Route::post('/', [AnnualBudgetController::class, 'store'])->name('store');
        Route::get('/{annual_budget}', [AnnualBudgetController::class, 'show'])->name('show');
        Route::get('/{annual_budget}/edit', [AnnualBudgetController::class, 'edit'])->name('edit');
        Route::put('/{annual_budget}', [AnnualBudgetController::class, 'update'])->name('update');
        Route::delete('/{annual_budget}', [AnnualBudgetController::class, 'destroy'])->name('destroy');
        Route::get('/{annual_budget}/approve', [AnnualBudgetController::class, 'approve'])->name('approve');
        Route::post('/{annual_budget}/approve', [AnnualBudgetController::class, 'approveStore'])->name('approve.store');

        Route::prefix('budgets')->name('budgets.')->group(function () {
            Route::get('/available-categories-month', [AnnualBudgetController::class, 'getAvailableCategoriesForMonth'])
                ->name('available-categories-month');
            Route::get('/check-availability', [AnnualBudgetController::class, 'checkAvailability'])
                ->name('check-availability');
        });
    });

    // ========================================================================
    //  Monthly Budget Distributions
    // ========================================================================
    Route::middleware('module.access:budget_control')->prefix('budget_monthly_distributions')->name('budget_monthly_distributions.')->group(function () {
        Route::get('/', [BudgetMonthlyDistributionController::class, 'index'])->name('index');
        Route::get('/datatable', [BudgetMonthlyDistributionController::class, 'datatable'])->name('datatable');
        Route::get('/{annual_budget}/create', [BudgetMonthlyDistributionController::class, 'create'])->name('create');
        Route::post('/', [BudgetMonthlyDistributionController::class, 'store'])->name('store');
        Route::get('/{annual_budget}/matrix', [BudgetMonthlyDistributionController::class, 'matrix'])->name('matrix');
        Route::get('/{budget_monthly_distribution}', [BudgetMonthlyDistributionController::class, 'show'])->name('show');
        Route::get('/{annual_budget}/edit', [BudgetMonthlyDistributionController::class, 'edit'])->name('edit');
        Route::put('/{annual_budget}', [BudgetMonthlyDistributionController::class, 'update'])->name('update');
    });

    // ========================================================================
    //  Budget Movements
    // ========================================================================
    Route::middleware('module.access:budget_control')->group(function () {
        Route::get('budget_movements/dashboard/critical', [BudgetMovementController::class, 'criticalDashboard'])->name('budget_movements.dashboard');
        Route::get('budget_movements/check-budget/availability', [BudgetMovementController::class, 'checkBudgetAvailability'])->name('budget_movements.check_budget');
        Route::resource('budget_movements', BudgetMovementController::class)->parameters(['budget_movements' => 'budgetMovement']);
        Route::post('budget_movements/{budgetMovement}/approve', [BudgetMovementController::class, 'approve'])->name('budget_movements.approve');
        Route::post('budget_movements/{budgetMovement}/reject', [BudgetMovementController::class, 'reject'])->name('budget_movements.reject');
    });

    // ========================================================================
    //  Requisitions CRUD
    // ========================================================================
    Route::middleware('module.access:requisitions')->controller(RequisitionController::class)->prefix('requisitions')->name('requisitions.')->group(function () {
        // DataTables (AJAX) - deben ir antes de rutas con parámetros
        Route::get('/datatable', 'datatable')->name('datatable');
        Route::get('/approval_datatable', 'approvalDatatable')->name('approval_datatable');

        // Flujo de Compras (AJAX para Modales)
        Route::get('/{requisition}/review-data', 'reviewData')->name('review-data');
        Route::post('/{requisition}/validate-technical', 'validateTechnical')->name('validate-technical');
        Route::post('/{requisition}/reject', 'reject')->name('reject');

        // CRUD
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/create-livewire', 'createLivewire')->name('create-livewire');
        Route::get('/{requisition}/edit-livewire', 'editLivewire')->name('edit-livewire');
        Route::post('/', 'store')->name('store');
        Route::get('/{requisition}', 'show')->name('show');
        Route::get('/{requisition}/edit', 'edit')->name('edit');
        Route::put('/{requisition}', 'update')->name('update');
        Route::delete('/{requisition}', 'destroy')->name('destroy');
        Route::put('/{requisition}/cancel', 'cancel')->name('cancel');
    });

    // ========================================================================
    //  Requisitions Workflow
    // ========================================================================
    Route::middleware('module.access:requisitions')->prefix('requisitions')->name('requisitions.')->group(function () {
        // Bandejas de workflow
        Route::get('/inbox/validation', [RequisitionWorkflowController::class, 'validationInbox'])->name('inbox.validation');

        // Vista de revisión/validación
        Route::get('/{requisition}/validate', [RequisitionWorkflowController::class, 'showValidationPage'])->name('validate.show');

        // Transiciones de workflow
        Route::post('/{requisition}/submit', [RequisitionWorkflowController::class, 'submit'])->name('submit');
        Route::post('/{requisition}/hold', [RequisitionWorkflowController::class, 'hold'])->name('hold');
        Route::post('/{requisition}/feedback', [RequisitionWorkflowController::class, 'feedback'])->name('feedback');
        Route::post('/{requisition}/validate', [RequisitionWorkflowController::class, 'approveForQuotation'])->name('validate');
        Route::post('/{requisition}/cancel', [RequisitionWorkflowController::class, 'cancel'])->name('workflow.cancel');
        Route::post('/{requisition}/reject', [RequisitionWorkflowController::class, 'reject'])->name('workflow.reject');
        Route::post('/{requisition}/consume', [RequisitionWorkflowController::class, 'consume'])->name('consume');
    });

    // ========================================================================
    //  Quotation Planner
    // ========================================================================
    Route::middleware('module.access:quotations')->prefix('requisitions/{requisition}/quotation-planner')
        ->name('requisitions.quotation-planner.')
        ->group(function () {
            Route::get('/', [QuotationPlannerController::class, 'show'])->name('show');
            Route::post('/save', [QuotationPlannerController::class, 'saveStrategy'])->name('save');
            Route::post('/groups', [QuotationPlannerController::class, 'createGroup'])->name('groups.create');
            Route::delete('/groups/{group}', [QuotationPlannerController::class, 'deleteGroup'])->name('groups.delete');
            Route::post('/groups/{group}/items', [QuotationPlannerController::class, 'addItemsToGroup'])->name('groups.items.add');
            Route::delete('/groups/{group}/items', [QuotationPlannerController::class, 'removeItemsFromGroup'])->name('groups.items.remove');
            Route::get('/groups/{group}/suggestions', [QuotationPlannerController::class, 'getSupplierSuggestions'])->name('groups.suggestions');
        });

    // ========================================================================
    //  RFQ (Request for Quotation)
    // ========================================================================
    Route::middleware('module.access:quotations')->prefix('rfq')->name('rfq.')->controller(RfqController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/datatable', 'datatable')->name('datatable');
        Route::get('/wizard/{requisition}/summary', 'wizardSummary')->name('wizard.summary');
        Route::get('/wizard/{requisition}/datatable', 'wizardDatatable')->name('wizard.datatable');
        Route::get('/summary', 'summary')->name('summary');
        Route::get('/inbox/received', 'receivedInbox')->name('inbox.received');

        Route::get('/select-suppliers/{requisition}', 'selectSuppliers')->name('select-suppliers');
        Route::post('/create/{requisition}', 'createRFQs')->name('create');
        Route::get('/compare/{requisition}', 'compare')->name('compare');
        Route::post('/approve/{requisition}', 'approve')->name('approve');

        Route::post('/{rfq}/send-single', 'sendSingle')->name('send.single');
        Route::post('/{rfq}/send', 'send')->name('send');
        Route::get('/{rfq}/requisition-modal', 'requisitionModal')->name('requisition-modal');
        Route::get('/{rfq}', 'show')->name('show')->where('rfq', '[0-9]+');
        Route::post('/{rfq}/cancel', 'cancelRFQ')->name('cancel');
    });

    // RFQ Inbox & Analysis
    Route::middleware('module.access:quotations')->prefix('rfq')->name('rfq.')->group(function () {
        Route::get('/wizard/{requisition}/analysis-data', [RfqInboxController::class, 'analysisData'])->name('wizard.analysis.data');

        Route::prefix('inbox')->name('inbox.')->group(function () {
            Route::get('/pending', [RfqInboxController::class, 'pending'])->name('pending');
            Route::get('/pending/data', [RfqInboxController::class, 'pendingData'])->name('pending.data');
            Route::get('/modal-rfq/{rfq}', [RfqInboxController::class, 'rfqModalContent'])->name('modal.rfq');
            Route::get('/modal-req/{requisition}', [RfqInboxController::class, 'reqModalContent'])->name('modal.req');
        });
    });

    // RFQ Comparison
    Route::middleware('module.access:quotations')->get('rfq/{rfq}/comparison', [RfqComparisonController::class, 'index'])->name('rfq.comparison.index');

    // RFQ Manage & Wizard
    Route::middleware('module.access:quotations')->get('/rfq/manage', fn () => view('rfq.manage'))->name('quotes.index');
    Route::middleware('module.access:quotations')->get('/rfq/wizard/{requisition}', function (Requisition $requisition) {
        return view('rfq.wizard', compact('requisition'));
    })->name('rfq.wizard.steps');

    // ========================================================================
    //  Products & Services Catalog
    // ========================================================================
    Route::middleware('module.access:products_services')->prefix('products-services')->name('products-services.')->group(function () {
        Route::get('/', [ProductServiceController::class, 'index'])->name('index');
        Route::get('/datatable', [ProductServiceController::class, 'datatable'])->name('datatable');
        Route::get('/create', [ProductServiceController::class, 'create'])->name('create');
        Route::post('/', [ProductServiceController::class, 'store'])->name('store');
        Route::get('/{productService}', [ProductServiceController::class, 'show'])->name('show');
        Route::get('/{productService}/edit', [ProductServiceController::class, 'edit'])->name('edit');
        Route::put('/{productService}', [ProductServiceController::class, 'update'])->name('update');
        Route::delete('/{productService}', [ProductServiceController::class, 'destroy'])->name('destroy');

        Route::post('/{productService}/approve', [ProductServiceController::class, 'approve'])->name('approve');
        Route::post('/{productService}/reject', [ProductServiceController::class, 'reject'])->name('reject');
        Route::post('/{productService}/deactivate', [ProductServiceController::class, 'deactivate'])->name('deactivate');
        Route::post('/{productService}/reactivate', [ProductServiceController::class, 'reactivate'])->name('reactivate');

        Route::get('/api/active', [ProductServiceController::class, 'apiActive'])->name('api.active');
        Route::post('/from-requisition', [ProductServiceController::class, 'storeFromRequisition'])->name('store-from-requisition');
    });

    // ========================================================================
    //  API Routes (internal)
    // ========================================================================
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/suppliers/search', [SupplierController::class, 'search'])->name('suppliers.search');

        Route::middleware('module.access:budget_control')->get('/annual_budgets/{annual_budget}/distributions', [BudgetMonthlyDistributionController::class, 'getByBudget'])
            ->name('annual_budgets.distributions');
        Route::middleware('module.access:budget_control')->get('/annual_budgets/{annual_budget}/check-availability/{month}/{categoryId}', [BudgetMonthlyDistributionController::class, 'checkAvailability'])
            ->name('annual_budgets.check-availability');

        Route::middleware('module.access:budget_control')->get('/budget/check-availability', [ExpenseCategoryController::class, 'checkBudget'])->name('budget.check-availability');
    });

    // Expense Categories
    Route::middleware('module.access:requisitions')->get('/products-services/api/active-for-requisitions', [ProductServiceController::class, 'apiActiveForRequisitions'])
        ->name('products-services.api.active-for-requisitions');

    Route::middleware('module.access:requisitions')->prefix('expense-categories')->name('expense-categories.')->group(function () {
        Route::post('/', [ExpenseCategoryController::class, 'store'])->name('store');
        Route::get('/select', [ExpenseCategoryController::class, 'getForSelect'])->name('select');
        Route::get('/by-budget', [ExpenseCategoryController::class, 'byBudget'])->name('by-budget');
        Route::get('/by-cost-center', [ExpenseCategoryController::class, 'getByCostCenter'])->name('by-cost-center');
        Route::get('/cedulas-by-cost-center', [ExpenseCategoryController::class, 'getCedulasByCostCenter'])->name('cedulas-by-cost-center');
    });

    Route::middleware('module.access:requisitions')
        ->get('requisitions/api/companies/{company}/cost-centers', [CostCenterController::class, 'byCompany'])
        ->name('requisitions.cost-centers.by-company');

    Route::get('requisitions/api/companies/{company}/receiving-locations', [RequisitionController::class, 'receivingLocationsByCompany'])
        ->name('requisitions.receiving-locations.by-company');
}); // Fin del grupo auth + lock

// ============================================================================
//  Supplier Portal (auth:supplier) - UNIFICADO
// ============================================================================
Route::middleware(['auth:supplier'])->prefix('supplier')->name('supplier.')->group(function () {
    // Dashboard
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->get('/dashboard', [SupplierPortalController::class, 'dashboard'])->name('dashboard');

    // Announcements
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->prefix('announcements')->name('announcements.')->group(function () {
        Route::get('/', [AnnouncementController::class, 'inbox'])->name('inbox');
        Route::get('/datatable', [AnnouncementController::class, 'supplierDatatable'])->name('datatable');
        Route::get('/{announcement}/pdf', [AnnouncementController::class, 'pdf'])->name('pdf');
        Route::get('/{announcement}', [AnnouncementController::class, 'show'])->name('show');
        Route::post('/{announcement}/view', [AnnouncementController::class, 'markViewed'])->name('view');
        Route::post('/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('dismiss');
    });

    // Documents
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [SupplierDocumentController::class, 'index'])->name('index');
        Route::post('/{supplier}', [SupplierDocumentController::class, 'store'])->name('store');
        Route::delete('/{supplier}/{document}', [SupplierDocumentController::class, 'destroy'])->name('destroy');
    });

    Route::patch('/{supplier}/bank', [SupplierBankController::class, 'update'])->name('bank.update');
    Route::delete('/{supplier}/bank', [SupplierBankController::class, 'destroy'])->name('bank.destroy');
    Route::patch('/{supplier}/repse', [SupplierBankController::class, 'updateRepse'])->name('repse.update');
    Route::prefix('{supplier}/sirocs')->name('sirocs.')->group(function () {
        Route::get('/', [SupplierSirocController::class, 'index'])->name('index');
        Route::get('/create', [SupplierSirocController::class, 'create'])->name('create');
        Route::post('/', [SupplierSirocController::class, 'store'])->name('store');
        Route::get('/{siroc}', [SupplierSirocController::class, 'show'])->name('show');
        Route::get('/{siroc}/edit', [SupplierSirocController::class, 'edit'])->name('edit');
        Route::put('/{siroc}', [SupplierSirocController::class, 'update'])->name('update');
        Route::delete('/{siroc}', [SupplierSirocController::class, 'destroy'])->name('destroy');
    });

    // RFQ
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->get('/rfq/{rfq}', [SupplierPortalController::class, 'showRfq'])->name('rfq.show');
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->post('/rfq/{rfq}/quotation', [SupplierPortalController::class, 'saveQuotation'])->name('rfq.quotation.save');

    // Quotation History
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->get('/quotations/history', [SupplierPortalController::class, 'quotationHistory'])->name('quotations.history');

    // Download attachment
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->get('/quotation/{response}/download', [SupplierPortalController::class, 'downloadAttachment'])->name('quotation.download');

    // Delete draft
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->delete('/quotation/{response}/draft', [SupplierPortalController::class, 'deleteDraft'])->name('quotation.draft.delete');

    // Deliveries (registro de entrega física con remisión)
    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [SupplierDeliveryController::class, 'index'])->name('index');   // supplier.deliveries.index
        Route::get('/create', [SupplierDeliveryController::class, 'create'])->name('create'); // supplier.deliveries.create
        Route::post('/', [SupplierDeliveryController::class, 'store'])->name('store');  // supplier.deliveries.store
    });

    Route::middleware(\App\Http\Middleware\EnsureSupplierIsApproved::class)->prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [SupplierInvoiceController::class, 'index'])->name('index');
        Route::get('/create', [SupplierInvoiceController::class, 'create'])->name('create');
        Route::post('/', [SupplierInvoiceController::class, 'store'])->name('store');
    });
});

// ============================================================================
//  Tools (superadmin)
// ============================================================================
Route::middleware(['auth', 'lock', 'role:superadmin'])->prefix('tools')->name('tools.')->group(function () {
    Route::get('cfdi-generator', [CfdiGeneratorController::class, 'form'])->name('cfdi.form');
    Route::post('cfdi-generator/xml', [CfdiGeneratorController::class, 'downloadXml'])->name('cfdi.xml');
    Route::post('cfdi-generator/pdf', [CfdiGeneratorController::class, 'downloadPdf'])->name('cfdi.pdf');
});

// ============================================================================
//  Approval Levels & Quotation Approvals (superadmin)
// ============================================================================
Route::middleware(['auth', 'lock', 'module.access:catalogs_config'])->group(function () {
    Route::resource('approval-levels', ApprovalLevelController::class)
        ->only(['index', 'edit', 'update'])
        ->names('approval-levels');

    Route::resource('authorizer-roles', AuthorizerRoleController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('authorizer-roles');

    // SAT Retenciones
    Route::get('sat-retenciones/datatable', [SatRetencionController::class, 'datatable'])
        ->name('sat-retenciones.datatable');
    Route::resource('sat-retenciones', SatRetencionController::class)
        ->except(['show'])
        ->parameters(['sat-retenciones' => 'sat_retencion']);
});

// ============================================================================
//  Tools (superadmin)
// ============================================================================
Route::middleware(['auth', 'lock', 'role:superadmin'])->prefix('tools')->name('tools.')->group(function () {
    Route::get('cfdi-generator', [CfdiGeneratorController::class, 'form'])->name('cfdi.form');
    Route::post('cfdi-generator/xml', [CfdiGeneratorController::class, 'downloadXml'])->name('cfdi.xml');
    Route::post('cfdi-generator/pdf', [CfdiGeneratorController::class, 'downloadPdf'])->name('cfdi.pdf');
});

Route::middleware(['auth', 'lock', 'module.access:quotations'])->group(function () {
    Route::get('/approvals/quotations', [QuotationApprovalController::class, 'index'])->name('approvals.quotations.index');
    Route::post('/approvals/quotations/{summary}/handle', [QuotationApprovalController::class, 'handle'])->name('approvals.quotations.handle');
});

Route::middleware(['auth', 'lock'])->group(function () {
    Route::get('/authorizations', [AuthorizationInboxController::class, 'index'])
        ->name('authorizations.index');

    Route::get('/my-delegation', [ApprovalDelegationController::class, 'index'])
        ->name('approval-delegations.index');
    Route::post('/my-delegation/activate', [ApprovalDelegationController::class, 'activate'])
        ->name('approval-delegations.activate');
    Route::put('/my-delegation', [ApprovalDelegationController::class, 'update'])
        ->name('approval-delegations.update');
    Route::post('/my-delegation/deactivate', [ApprovalDelegationController::class, 'deactivate'])
        ->name('approval-delegations.deactivate');
});

Route::middleware(['auth', 'lock', 'role:superadmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/approval-delegations', [AdminApprovalDelegationController::class, 'index'])
            ->name('approval-delegations.index');
        Route::post('/approval-delegations/{delegation}/deactivate', [AdminApprovalDelegationController::class, 'deactivate'])
            ->name('approval-delegations.deactivate');
    });

Route::middleware(['auth', 'lock', 'module.access:payments_billing'])->group(function () {
    Route::get('/invoices', [FinanceInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [FinanceInvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [FinanceInvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [FinanceInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/reject', [FinanceInvoiceController::class, 'reject'])->name('invoices.reject');

    Route::get('/financial-provisions', [FinancialProvisionController::class, 'index'])->name('financial-provisions.index');
    Route::get('/financial-provisions/{financialProvision}', [FinancialProvisionController::class, 'show'])->name('financial-provisions.show');
    Route::post('/financial-provisions/{financialProvision}/link-invoice', [FinancialProvisionController::class, 'linkInvoice'])
        ->name('financial-provisions.link-invoice');
    Route::post('/financial-provisions/{financialProvision}/adjustments', [FinancialProvisionController::class, 'authorizeAdjustment'])
        ->name('financial-provisions.adjustments.store');
});

// ============================================================================
//  Purchase Orders & RFQ Selection (superadmin | buyer)
// ============================================================================
Route::middleware(['auth', 'lock'])->group(function () {
    // RFQ Selection
    Route::middleware('module.access:quotations')->post('/rfq/{rfq}/select', [RfqComparisonController::class, 'select'])->name('rfq.comparison.select');
    Route::middleware('module.access:quotations')->post('/rfq/{rfq}/reaward', [RfqComparisonController::class, 'reaward'])->name('rfq.comparison.reaward');
    Route::middleware('module.access:quotations')->post('/rfq/{rfq}/cancel-rejected', [RfqComparisonController::class, 'cancelRejected'])->name('rfq.comparison.cancel-rejected');
    Route::middleware('module.access:quotations')->post('/rfq/{rfq}/generate-complementary', [RfqComparisonController::class, 'generateComplementaryRfq'])->name('rfq.comparison.generate-complementary');

    // Direct Purchase Orders
    Route::middleware('module.access:purchase_orders')->get('/direct-purchase-orders/create', [DirectPurchaseOrderController::class, 'create'])->name('direct-purchase-orders.create');
    Route::middleware('module.access:purchase_orders')->post('/direct-purchase-orders', [DirectPurchaseOrderController::class, 'store'])->name('direct-purchase-orders.store');
    Route::middleware('module.access:purchase_orders')->get('/direct-purchase-orders/{directPurchaseOrder}/edit', [DirectPurchaseOrderController::class, 'edit'])->name('direct-purchase-orders.edit');
    Route::middleware('module.access:purchase_orders')->put('/direct-purchase-orders/{directPurchaseOrder}', [DirectPurchaseOrderController::class, 'update'])->name('direct-purchase-orders.update');
    Route::middleware('module.access:purchase_orders')->get('/direct-purchase-orders/categories', [DirectPurchaseOrderController::class, 'getAvailableCategories'])->name('direct-purchase-orders.categories');
    Route::middleware('module.access:purchase_orders')->get('/direct-purchase-orders/{directPurchaseOrder}', [PurchaseOrderController::class, 'showDirect'])->name('direct-purchase-orders.show');
    Route::middleware('module.access:purchase_orders')->post('/direct-purchase-orders/{directPurchaseOrder}/submit', [DirectPurchaseOrderController::class, 'submit'])->name('direct-purchase-orders.submit');
    Route::middleware('module.access:purchase_orders')->post('/direct-purchase-orders/{directPurchaseOrder}/approve', [DirectPurchaseOrderController::class, 'approve'])->name('direct-purchase-orders.approve');
    Route::middleware('module.access:purchase_orders')->post('/direct-purchase-orders/{directPurchaseOrder}/reject', [DirectPurchaseOrderController::class, 'reject'])->name('direct-purchase-orders.reject');
    Route::middleware('module.access:purchase_orders')->post('/direct-purchase-orders/{directPurchaseOrder}/return', [DirectPurchaseOrderController::class, 'return'])->name('direct-purchase-orders.return');

    // Purchase Orders
    Route::middleware('module.access:purchase_orders')->get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::middleware('module.access:purchase_orders')->get('/purchase-orders/datatable/regular', [PurchaseOrderController::class, 'datatableRegular'])->name('purchase-orders.datatable.regular');
    Route::middleware('module.access:purchase_orders')->get('/purchase-orders/datatable/direct', [PurchaseOrderController::class, 'datatableDirect'])->name('purchase-orders.datatable.direct');
    Route::middleware('module.access:purchase_orders')->get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::middleware('module.access:purchase_orders')->post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::middleware('module.access:purchase_orders')->post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-orders.reject');

    // Recepciones — rutas estáticas ANTES de {reception} para evitar conflictos de parámetro
    Route::middleware('module.access:receptions')->get('/receptions/overview', [ReceptionController::class, 'overview'])->name('receptions.overview');
    Route::middleware('module.access:receptions')->get('/receptions/datatable/regular-pending', [ReceptionController::class, 'datatableRegularPending'])->name('receptions.datatable.regular-pending');
    Route::middleware('module.access:receptions')->get('/receptions/datatable/direct-pending', [ReceptionController::class, 'datatableDirectPending'])->name('receptions.datatable.direct-pending');
    Route::middleware('module.access:receptions')->get('/receptions/pending', [ReceptionController::class, 'pending'])->name('receptions.pending');
    Route::middleware('module.access:receptions')->get('/purchase-orders/{purchaseOrder}/receive', [ReceptionController::class, 'create'])->name('receptions.create');
    Route::middleware('module.access:receptions')->post('/purchase-orders/{purchaseOrder}/receive', [ReceptionController::class, 'store'])->name('receptions.store');
    Route::middleware('module.access:receptions')->get('/direct-purchase-orders/{directPurchaseOrder}/receive', [ReceptionController::class, 'createDirect'])->name('receptions.create-direct');
    Route::middleware('module.access:receptions')->post('/direct-purchase-orders/{directPurchaseOrder}/receive', [ReceptionController::class, 'storeDirect'])->name('receptions.store-direct');
    Route::middleware('module.access:receptions')->get('/receptions/{reception}', [ReceptionController::class, 'show'])->name('receptions.show');
    Route::middleware('module.access:receptions')->get('/receptions/{reception}/remission', [ReceptionController::class, 'downloadRemission'])->name('receptions.remission.download');

    // Receiving Locations (rutas específicas ANTES del resource para evitar conflictos con {id})
    Route::middleware('module.access:catalogs_config')->get('receiving-locations/data', [ReceivingLocationController::class, 'getData'])->name('receiving-locations.data');
    Route::middleware('module.access:catalogs_config')->post('receiving-locations/{receiving_location}/block-portal', [ReceivingLocationController::class, 'blockPortal'])->name('receiving-locations.block-portal');
    Route::middleware('module.access:catalogs_config')->post('receiving-locations/{receiving_location}/unblock-portal', [ReceivingLocationController::class, 'unblockPortal'])->name('receiving-locations.unblock-portal');
    Route::middleware('module.access:catalogs_config')->resource('receiving-locations', ReceivingLocationController::class);
});

// ============================================================================
//  Dev Tools (solo usuario id=1)
// ============================================================================
Route::get('/dev/logs', [LogViewerController::class, 'index'])->name('dev.log.index');
Route::delete('/dev/logs', [LogViewerController::class, 'clear'])->name('dev.log.clear');

// ============================================================================
//  Rutas comentadas (sin uso actual, conservadas por decisión)
// ============================================================================

// ============================================================================
//  Catálogo de Roles y Permisos (solo superadmin, solo lectura)
// ============================================================================
Route::middleware(['auth', 'lock', 'role:superadmin'])
    ->get('/roles/catalog', [RolesCatalogController::class, 'index'])
    ->name('roles.catalog');

// ============================================================================
//  CSRF Token Refresh (previene error 419 en formularios multi-paso)
//  El JS llama a este endpoint justo antes de enviar para obtener un token fresco.
// ============================================================================
Route::get('/csrf-refresh', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

// ============================================================================
//  Contratos Comerciales
// ============================================================================
Route::middleware(['auth', 'lock', 'role:buyer|superadmin'])
    ->controller(\App\Http\Controllers\ContractController::class)
    ->prefix('contracts')
    ->name('contracts.')
    ->group(function () {
        Route::get('/datatable', 'datatable')->name('datatable');
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/import', 'importForm')->name('import');
        Route::get('/template', 'downloadTemplate')->name('template');
        Route::post('/import/preview', 'importPreview')->name('import.preview');
        Route::post('/import/confirm', 'importConfirm')->name('import.confirm');
        Route::get('/requisition/create', 'requisitionCreate')->name('requisition.create');
        Route::get('/{contract}', 'show')->name('show');
        Route::get('/{contract}/edit', 'edit')->name('edit');
        Route::put('/{contract}', 'update')->name('update');
        Route::post('/{contract}/cancel', 'cancel')->name('cancel');
    });

// ============================================================================
//  Autenticación
// ============================================================================
require __DIR__.'/auth.php';
