<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 28px 30px; }
        * { box-sizing: border-box; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; line-height: 1.32; }
        .header { border-bottom: 3px solid #188ae2; padding-bottom: 12px; width: 100%; }
        .logo { max-height: 42px; max-width: 155px; }
        .title { color: #123a5c; font-size: 19px; font-weight: bold; margin: 0; text-align: right; }
        .folio { color: #188ae2; font-size: 11px; font-weight: bold; text-align: right; margin-top: 4px; }
        .meta { color: #64748b; font-size: 8px; text-align: right; margin-top: 3px; }
        .section-title { background: #edf7fe; border-left: 3px solid #188ae2; color: #123a5c; font-size: 9px; font-weight: bold; margin: 12px 0 6px; padding: 5px 7px; text-transform: uppercase; }
        .info { border-collapse: collapse; width: 100%; }
        .info td { border: 1px solid #dce7ef; padding: 5px 7px; vertical-align: top; }
        .label { color: #64748b; display: block; font-size: 6.5px; font-weight: bold; text-transform: uppercase; }
        .value { color: #1f2937; font-size: 8.5px; font-weight: bold; margin-top: 2px; }
        .items { border-collapse: collapse; margin-top: 6px; width: 100%; }
        .items th { background: #123a5c; color: #fff; font-size: 8px; padding: 7px 5px; text-align: left; }
        .items td { border-bottom: 1px solid #dce7ef; padding: 7px 5px; vertical-align: top; }
        .items tr:nth-child(even) td { background: #f8fbfd; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .totals { border-collapse: collapse; margin-left: auto; margin-top: 10px; width: 235px; }
        .totals td { border-bottom: 1px solid #dce7ef; padding: 5px 7px; }
        .totals .grand td { background: #eaf8f1; border-top: 2px solid #4bd396; color: #126346; font-size: 11px; font-weight: bold; }
        .notes { border: 1px solid #dce7ef; color: #475569; padding: 8px; }
        .signatures { border-collapse: collapse; margin-top: 34px; width: 100%; }
        .signature { padding: 0 4px; text-align: center; vertical-align: top; width: 25%; }
        .signature-line { border-top: 1px solid #64748b; margin: 0 auto 7px; width: 86%; }
        .signature-label { color: #1f2937; display: block; font-size: 7px; font-weight: bold; margin-bottom: 28px; text-transform: uppercase; }
        .signature-role { color: #475569; display: block; margin-top: 3px; }
        .footer { bottom: -20px; color: #94a3b8; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
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
        $authorizer = $purchaseOrder->approver ?? $purchaseOrder->assignedApprover;
        $supplierAddress = collect([
            $supplier?->address,
            $supplier?->postal_code ? 'C.P. '.$supplier->postal_code : null,
        ])->filter()->implode(' - ');
        $deliveryAddressDetails = collect([
            $receivingLocation?->address,
            $receivingLocation?->city,
            $receivingLocation?->state,
            $receivingLocation?->postal_code ? 'C.P. '.$receivingLocation->postal_code : null,
        ])->filter();
        $deliveryAddress = $deliveryAddressDetails->isNotEmpty()
            ? $deliveryAddressDetails->push($receivingLocation?->country)->filter()->implode(', ')
            : null;
    @endphp
    <table class="header"><tr>
        <td style="width:48%">@if(is_file($logoPath)) <img class="logo" src="{{ $logoPath }}" alt="TotalGas"> @else <strong style="font-size:17px;color:#188ae2">TOTALGAS</strong> @endif</td>
        <td style="width:52%"><h1 class="title">ORDEN DE COMPRA</h1><div class="folio">{{ $purchaseOrder->folio }}</div><div class="meta">Fecha de emisión: {{ ($purchaseOrder->issued_at ?? $purchaseOrder->created_at)?->format('d/m/Y') }}</div></td>
    </tr></table>

    <div class="section-title">Datos generales</div>
    <table class="info"><tr>
        <td style="width:42%"><span class="label">Empresa compradora</span><span class="value">{{ $company?->legal_name ?? $company?->name ?? '—' }}</span><br><span class="label" style="margin-top:5px">RFC</span><span class="value">{{ $company?->rfc ?? '—' }}</span>@if($company?->phone)<br><span class="label" style="margin-top:5px">Teléfono</span><span class="value">{{ $company->phone }}</span>@endif @if($company?->email)<br><span class="label" style="margin-top:5px">Correo</span><span class="value">{{ $company->email }}</span>@endif</td>
        <td style="width:58%"><span class="label">Proveedor</span><span class="value">{{ $supplier?->company_name ?? '—' }}</span><br><span class="label" style="margin-top:5px">RFC</span><span class="value">{{ $supplier?->rfc ?? '—' }}</span>@if($supplierAddress)<br><span class="label" style="margin-top:5px">Dirección</span><span class="value">{{ $supplierAddress }}</span>@endif<br><span class="label" style="margin-top:5px">Contacto</span><span class="value">{{ $supplier?->contact_person ?? '—' }}@if($supplier?->contact_phone) · {{ $supplier->contact_phone }}@endif</span>@if($supplier?->phone_number)<br><span class="label" style="margin-top:5px">Teléfono</span><span class="value">{{ $supplier->phone_number }}</span>@endif @if($supplier?->email)<br><span class="label" style="margin-top:5px">Correo</span><span class="value">{{ $supplier->email }}</span>@endif</td>
    </tr></table>

    <div class="section-title">Condiciones de la orden</div>
    <table class="info"><tr>
        <td style="width:34%"><span class="label">Requisición origen</span><span class="value">{{ $purchaseOrder->requisition?->folio ?? '—' }}</span></td>
        <td style="width:33%"><span class="label">Condiciones de pago</span><span class="value">{{ $purchaseOrder->payment_terms ?? '—' }}</span></td>
        <td style="width:33%"><span class="label">Tiempo estimado de entrega</span><span class="value">{{ $purchaseOrder->estimated_delivery_days ? $purchaseOrder->estimated_delivery_days.' días' : '—' }}</span></td>
    </tr></table>

    <div class="section-title">Entrega</div>
    <table class="info"><tr>
        <td style="width:60%"><span class="label">Punto de entrega</span><span class="value">{{ $receivingLocation ? $receivingLocation->code.' · '.$receivingLocation->name : '—' }}</span>@if($deliveryAddress)<br><span class="label" style="margin-top:5px">Dirección de entrega</span><span class="value">{{ $deliveryAddress }}</span>@endif @if($receivingLocation?->manager_name)<br><span class="label" style="margin-top:5px">Responsable de recepción</span><span class="value">{{ $receivingLocation->manager_name }}</span>@endif</td>
        <td style="width:40%"><span class="label">Solicitante</span><span class="value">{{ $purchaseOrder->requisition?->requester?->name ?? $purchaseOrder->creator?->name ?? '—' }}</span>@if($receivingLocation?->phone)<br><span class="label" style="margin-top:5px">Teléfono de entrega</span><span class="value">{{ $receivingLocation->phone }}</span>@endif @if($receivingLocation?->email)<br><span class="label" style="margin-top:5px">Correo de entrega</span><span class="value">{{ $receivingLocation->email }}</span>@endif</td>
    </tr></table>

    <div class="section-title">Partidas</div>
    <table class="items"><thead><tr><th style="width:5%">#</th><th>Descripción</th><th style="width:9%" class="center">Cantidad</th><th style="width:10%" class="center">Unidad</th><th style="width:13%" class="number">P. unitario</th><th style="width:9%" class="number">IVA</th><th style="width:14%" class="number">Importe</th></tr></thead>
        <tbody>@foreach($purchaseOrder->items as $index => $item) @php($rate = (float)$item->subtotal > 0 ? round(((float)$item->iva_amount / (float)$item->subtotal) * 100) : 0)<tr><td class="center">{{ $index + 1 }}</td><td><strong>{{ $item->description }}</strong>@if($item->requisitionItem?->costCenter)<br><span style="color:#64748b;font-size:7px">CC: {{ $item->requisitionItem->costCenter->code }} · {{ $item->requisitionItem->costCenter->name }}</span>@endif</td><td class="center">{{ number_format((float)$item->quantity, 2) }}</td><td class="center">{{ $item->requisitionItem?->unit ?? '—' }}</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$item->unit_price, 2) }}</td><td class="number">{{ $rate }}%</td><td class="number"><strong>{{ $currencySymbol }}{{ number_format((float)$item->total, 2) }}</strong></td></tr>@endforeach</tbody>
    </table>
    <table class="totals"><tr><td>Subtotal</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$purchaseOrder->subtotal, 2) }}</td></tr><tr><td>IVA</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$purchaseOrder->iva_amount, 2) }}</td></tr><tr class="grand"><td>TOTAL {{ $purchaseOrder->currency }}</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$purchaseOrder->total, 2) }}</td></tr></table>
    @if($purchaseOrder->requisition?->description)<div class="section-title">Observaciones</div><div class="notes">{{ $purchaseOrder->requisition->description }}</div>@endif
    <table class="signatures"><tr>
        <td class="signature"><span class="signature-label">Elaboró</span><div class="signature-line"></div><strong>{{ $requester?->name ?? '—' }}</strong><span class="signature-role">Requisitor</span></td>
        <td class="signature"><span class="signature-label">Solicita</span><div class="signature-line"></div><strong>{{ $buyer?->name ?? '—' }}</strong><span class="signature-role">Compras</span></td>
        <td class="signature"><span class="signature-label">Aceptó</span><div class="signature-line"></div><strong>{{ $supplier?->company_name ?? 'Proveedor' }}</strong><span class="signature-role">Proveedor</span></td>
        <td class="signature"><span class="signature-label">Autoriza</span><div class="signature-line"></div><strong>{{ $authorizer?->name ?? '—' }}</strong><span class="signature-role">{{ $authorizer?->job_title ?? 'Autorizador de la OC' }}</span></td>
    </tr></table>
    <div class="footer">Documento generado por Portal de Proveedores TotalGas · {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
