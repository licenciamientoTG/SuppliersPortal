<?php

namespace App\Http\Controllers;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierInvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService)
    {
    }

    public function index()
    {
        $supplier = Auth::guard('supplier')->user();
        abort_if(! $supplier, 403);

        $invoices = SupplierInvoice::with(['financialProvision.reception', 'receivable'])
            ->where('supplier_id', $supplier->id)
            ->latest()
            ->paginate(20);

        return view('supplier.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $supplier = Auth::guard('supplier')->user();
        abort_if(! $supplier, 403);

        $orders = $this->supplierOrders($supplier->id);

        return view('supplier.invoices.create', compact('orders'));
    }

    public function store(Request $request)
    {
        $supplier = Auth::guard('supplier')->user();
        abort_if(! $supplier, 403);

        $validated = $request->validate([
            'order_type' => 'required|in:standard,direct',
            'order_id' => 'required|integer',
            'xml_file' => 'required|file|mimes:xml|max:5120',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $order = $this->invoiceService->resolveOrder($validated['order_type'], (int) $validated['order_id']);
        abort_unless((int) $order->supplier_id === (int) $supplier->id, 403);

        $invoice = $this->invoiceService->upload(
            supplier: $supplier,
            order: $order,
            xmlFile: $request->file('xml_file'),
            pdfFile: $request->file('pdf_file'),
            uploader: Auth::guard('web')->user(),
            origin: SupplierInvoice::ORIGIN_SUPPLIER,
        );

        return redirect()
            ->route('supplier.invoices.index')
            ->with('success', "Factura {$invoice->uuid} cargada correctamente.");
    }

    private function supplierOrders(int $supplierId)
    {
        $regular = PurchaseOrder::where('supplier_id', $supplierId)
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->get()
            ->map(fn($order) => [
                'type' => 'standard',
                'id' => $order->id,
                'label' => "OC {$order->folio} - " . format_money($order->total, $order->currency),
            ]);

        $direct = DirectPurchaseOrder::where('supplier_id', $supplierId)
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->get()
            ->map(fn($order) => [
                'type' => 'direct',
                'id' => $order->id,
                'label' => "OCD {$order->folio} - " . format_money($order->total, $order->currency),
            ]);

        return $regular->merge($direct)->values();
    }
}
