<?php

namespace App\Http\Controllers;

use App\Models\FinancialProvision;
use App\Models\SupplierInvoice;
use App\Services\FinancialProvisionService;
use Illuminate\Http\Request;

class FinancialProvisionController extends Controller
{
    public function __construct(private FinancialProvisionService $financialProvisionService) {}

    public function index()
    {
        $provisions = FinancialProvision::with(['supplier', 'reception', 'invoice'])
            ->latest()
            ->paginate(25);

        return view('finance.provisions.index', compact('provisions'));
    }

    public function show(FinancialProvision $financialProvision)
    {
        $financialProvision->load([
            'supplier',
            'reception.items.receivableItem',
            'invoice',
            'adjustments.authorizer',
            'receivable',
        ]);
        $compatibleInvoices = SupplierInvoice::where('supplier_id', $financialProvision->supplier_id)
            ->where('receivable_type', $financialProvision->receivable_type)
            ->where('receivable_id', $financialProvision->receivable_id)
            ->whereNull('financial_provision_id')
            ->where('status', SupplierInvoice::STATUS_UPLOADED)
            ->latest()
            ->get();

        return view('finance.provisions.show', [
            'provision' => $financialProvision,
            'compatibleInvoices' => $compatibleInvoices,
        ]);
    }

    public function linkInvoice(Request $request, FinancialProvision $financialProvision)
    {
        $validated = $request->validate([
            'supplier_invoice_id' => 'required|integer|exists:supplier_invoices,id',
        ]);

        abort_unless($financialProvision->status === FinancialProvision::STATUS_PENDING_INVOICE, 422);

        $invoice = SupplierInvoice::where('id', $validated['supplier_invoice_id'])
            ->where('supplier_id', $financialProvision->supplier_id)
            ->where('receivable_type', $financialProvision->receivable_type)
            ->where('receivable_id', $financialProvision->receivable_id)
            ->whereNull('financial_provision_id')
            ->where('status', SupplierInvoice::STATUS_UPLOADED)
            ->firstOrFail();

        $this->financialProvisionService->reconcile($financialProvision, $invoice);

        return redirect()
            ->route('financial-provisions.show', $financialProvision)
            ->with('success', 'Factura vinculada y conciliada correctamente.');
    }
}
