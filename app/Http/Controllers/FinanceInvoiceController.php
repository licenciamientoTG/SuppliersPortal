<?php

namespace App\Http\Controllers;

use App\Models\DirectPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\InvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class FinanceInvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService)
    {
    }

    public function index()
    {
        $invoices = SupplierInvoice::with(['supplier', 'financialProvision.reception', 'receivable'])
            ->latest()
            ->paginate(25);

        return view('finance.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('company_name')->get(['id', 'company_name', 'rfc']);
        $orders = $this->orders();

        return view('finance.invoices.create', compact('suppliers', 'orders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'order_type' => 'required|in:standard,direct',
            'order_id' => 'required|integer',
            'xml_file' => 'required|file|mimes:xml|max:5120',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $order = $this->invoiceService->resolveOrder($validated['order_type'], (int) $validated['order_id']);
        abort_unless((int) $order->supplier_id === (int) $supplier->id, 422, 'La orden no pertenece al proveedor seleccionado.');

        try {
            $invoice = $this->invoiceService->upload(
                supplier: $supplier,
                order: $order,
                xmlFile: $request->file('xml_file'),
                pdfFile: $request->file('pdf_file'),
                uploader: Auth::user(),
                origin: SupplierInvoice::ORIGIN_FINANCE,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Finance invoice upload failed.', [
                'supplier_id' => $supplier->id,
                'order_type' => $validated['order_type'],
                'order_id' => (int) $validated['order_id'],
                'exception' => $exception,
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'xml_file' => $this->resolveUploadErrorMessage($exception),
                ]);
        }

        return redirect()
            ->route('invoices.index')
            ->with('success', "Factura {$invoice->uuid} cargada correctamente.");
    }

    public function show(SupplierInvoice $invoice)
    {
        $invoice->load(['supplier', 'financialProvision.reception', 'receivable', 'uploader']);

        return view('finance.invoices.show', compact('invoice'));
    }

    public function reject(Request $request, SupplierInvoice $invoice)
    {
        abort_unless($invoice->status === SupplierInvoice::STATUS_UPLOADED, 422, 'Solo se pueden rechazar facturas no vinculadas.');

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:1000',
        ]);

        $this->invoiceService->reject($invoice, $validated['rejection_reason']);

        return back()->with('success', 'Factura rechazada correctamente.');
    }

    private function orders()
    {
        $regular = PurchaseOrder::with('supplier')
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->get()
            ->map(fn($order) => [
                'type' => 'standard',
                'id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'label' => "OC {$order->folio} - {$order->supplier?->company_name}",
            ]);

        $direct = DirectPurchaseOrder::with('supplier')
            ->whereIn('status', ['ISSUED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'DELIVERED_PENDING_RECEPTION'])
            ->get()
            ->map(fn($order) => [
                'type' => 'direct',
                'id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'label' => "OCD {$order->folio} - {$order->supplier?->company_name}",
            ]);

        return collect($regular)->merge($direct)->values();
    }

    private function resolveUploadErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            $message = trim($exception->getPrevious()?->getMessage() ?: $exception->getMessage());

            return $message !== ''
                ? "Error al guardar la factura: {$message}"
                : 'Ocurrió un error de base de datos al guardar la factura.';
        }

        $message = trim($exception->getMessage());

        return $message !== ''
            ? $message
            : 'Ocurrió un error inesperado al procesar la factura. Si el problema continúa, contacta a soporte.';
    }
}
