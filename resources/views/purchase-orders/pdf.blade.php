<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('purchase-orders.partials.pdf-styles')
</head>
<body>
    @php
        $currencySymbol = ($purchaseOrder->currency ?? 'MXN') === 'USD' ? 'US$' : '$';
        $company = $purchaseOrder->requisition?->company;
        $supplier = $purchaseOrder->supplier;
        $receivingLocation = $purchaseOrder->receivingLocation;
        $requester = $purchaseOrder->requisition?->requester;
        $buyer = $purchaseOrder->quotationSummary?->selector
            ?? $purchaseOrder->quotationSummary?->rfq?->creator
            ?? $purchaseOrder->creator;
        // Las OC regulares se emiten tras aprobar el comparativo; su aprobador queda en QuotationSummary.
        $authorizer = $purchaseOrder->approver
            ?? $purchaseOrder->quotationSummary?->approver
            ?? $purchaseOrder->assignedApprover;
        $supplierAddress = collect([
            $supplier?->address,
            $supplier?->postal_code ? 'C.P. '.$supplier->postal_code : null,
        ])->filter()->implode(' - ');
        $supplierContact = collect([
            $supplier?->contact_person,
            $supplier?->contact_phone ?? $supplier?->phone_number,
            $supplier?->email,
        ])->filter()->implode(' · ');
        $deliveryAddressDetails = collect([
            $receivingLocation?->address,
            $receivingLocation?->city,
            $receivingLocation?->state,
            $receivingLocation?->postal_code ? 'C.P. '.$receivingLocation->postal_code : null,
        ])->filter();
        $deliveryAddress = $deliveryAddressDetails->isNotEmpty()
            ? $deliveryAddressDetails->push($receivingLocation?->country)->filter()->implode(', ')
            : null;
        $deliveryContact = collect([
            $receivingLocation?->manager_name ? 'Recibe: '.$receivingLocation->manager_name : null,
            $receivingLocation?->phone,
            $receivingLocation?->email,
        ])->filter()->implode(' · ');
        $signatures = [
            ['Elaboró', $requester?->name ?? '—', 'Requisitor'],
            ['Solicita', $buyer?->name ?? '—', 'Compras'],
            ['Aceptó', $supplier?->company_name ?? 'Proveedor', 'Proveedor'],
            ['Autoriza', $authorizer?->name ?? '—', $authorizer?->job_title ?? 'Autorizador de la OC'],
        ];
    @endphp

    @include('purchase-orders.partials.pdf-letterhead', [
        'title' => 'Orden de compra',
        'folio' => $purchaseOrder->folio,
    ])

    <table class="kpis"><tr>
        <td><div class="kpi"><div class="k">Fecha de emisión</div><div class="v">{{ ($purchaseOrder->issued_at ?? $purchaseOrder->created_at)?->format('d/m/Y') ?? '—' }}</div></div></td>
        <td><div class="kpi kpi-sky"><div class="k">Requisición</div><div class="v">{{ $purchaseOrder->requisition?->folio ?? '—' }}</div></div></td>
        <td><div class="kpi kpi-green"><div class="k">Condiciones de pago</div><div class="v">{{ $purchaseOrder->payment_terms ?? '—' }}</div></div></td>
        <td class="last"><div class="kpi kpi-lime"><div class="k">Entrega estimada</div><div class="v">{{ $purchaseOrder->estimated_delivery_days ? $purchaseOrder->estimated_delivery_days.' días' : '—' }}</div></div></td>
    </tr></table>

    <table class="parties"><tr>
        <td>
            <div class="party-t">Proveedor</div>
            <div class="party-n">{{ $supplier?->company_name ?? '—' }}</div>
            <div class="sub">RFC {{ $supplier?->rfc ?? '—' }}</div>
            @if($supplierAddress)<div class="sub">{{ $supplierAddress }}</div>@endif
            @if($supplierContact)<div class="sub">{{ $supplierContact }}</div>@endif
        </td>
        <td class="last">
            <div class="party-t">Entregar en</div>
            <div class="party-n">{{ $receivingLocation ? $receivingLocation->code.' · '.$receivingLocation->name : '—' }}</div>
            @if($deliveryAddress)<div class="sub">{{ $deliveryAddress }}</div>@endif
            @if($deliveryContact)<div class="sub">{{ $deliveryContact }}</div>@endif
            <div class="sub">Solicitante: {{ $requester?->name ?? $purchaseOrder->creator?->name ?? '—' }}</div>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr>
            <th style="width:5%">No.</th>
            <th>Descripción</th>
            <th class="ctr" style="width:8%">Cant.</th>
            <th class="ctr" style="width:8%">Unidad</th>
            <th class="r" style="width:13%">P. unitario</th>
            <th class="r" style="width:7%">IVA</th>
            <th class="r" style="width:14%">Importe</th>
        </tr></thead>
        <tbody>
        @foreach($purchaseOrder->items as $item)
            @php($rate = (float) $item->subtotal > 0 ? round(((float) $item->iva_amount / (float) $item->subtotal) * 100) : 0)
            <tr>
                <td class="idx">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                <td>
                    <b>{{ $item->description }}</b>
                    @if($item->requisitionItem?->costCenter)<br><span class="muted">{{ $item->requisitionItem->costCenter->code }} · {{ $item->requisitionItem->costCenter->name }}</span>@endif
                    @if($item->requisitionItem?->notes)<br><span class="note">Nota para proveedor: {{ $item->requisitionItem->notes }}</span>@endif
                </td>
                <td class="ctr">{{ number_format((float) $item->quantity, 2) }}</td>
                <td class="ctr">{{ $item->requisitionItem?->unit ?? '—' }}</td>
                <td class="r">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="r">{{ $rate }}%</td>
                <td class="r"><b>{{ $currencySymbol }}{{ number_format((float) $item->total, 2) }}</b></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="summary"><tr>
        <td style="width:56%;padding-right:24px">
            @if($purchaseOrder->requisition?->description)
                <div class="obs-t">Observaciones</div>
                <div class="obs">{{ $purchaseOrder->requisition->description }}</div>
            @endif
        </td>
        <td style="width:44%">
            <table class="st">
                <tr><td class="sub">Subtotal</td><td class="r">{{ $currencySymbol }}{{ number_format((float) $purchaseOrder->subtotal, 2) }}</td></tr>
                <tr><td class="sub">IVA</td><td class="r">{{ $currencySymbol }}{{ number_format((float) $purchaseOrder->iva_amount, 2) }}</td></tr>
            </table>
            <div class="total-k">Total {{ $purchaseOrder->currency ?? 'MXN' }}</div>
            <div class="total-v">{{ $currencySymbol }}{{ number_format((float) $purchaseOrder->total, 2) }}</div>
            <div class="total-u"></div>
        </td>
    </tr></table>

    <table class="signs"><tr>
        @foreach($signatures as [$label, $name, $role])
            <td style="width:25%"><div class="sbox"><div class="k">{{ $label }}</div></div><div class="sname">{{ $name }}</div><div class="srole">{{ $role }}</div></td>
        @endforeach
    </tr></table>
</body>
</html>
