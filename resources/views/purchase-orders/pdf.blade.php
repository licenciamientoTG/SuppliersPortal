<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 28px 34px; }
        * { box-sizing: border-box; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
        .header { border-bottom: 3px solid #188ae2; padding-bottom: 12px; width: 100%; }
        .logo { max-height: 42px; max-width: 155px; }
        .title { color: #123a5c; font-size: 19px; font-weight: bold; margin: 0; text-align: right; }
        .folio { color: #188ae2; font-size: 11px; font-weight: bold; text-align: right; margin-top: 4px; }
        .meta { color: #64748b; font-size: 8px; text-align: right; margin-top: 3px; }
        .section-title { background: #edf7fe; border-left: 3px solid #188ae2; color: #123a5c; font-size: 9px; font-weight: bold; margin: 16px 0 7px; padding: 5px 7px; text-transform: uppercase; }
        .info { border-collapse: collapse; width: 100%; }
        .info td { border: 1px solid #dce7ef; padding: 6px 7px; vertical-align: top; width: 33.33%; }
        .label { color: #64748b; display: block; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .value { color: #1f2937; font-size: 9px; font-weight: bold; margin-top: 2px; }
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
        .signature { margin-top: 46px; text-align: center; width: 46%; }
        .signature-line { border-top: 1px solid #64748b; margin: 0 auto 5px; width: 88%; }
        .footer { bottom: -20px; color: #94a3b8; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    @php
        $currencySymbol = ($purchaseOrder->currency ?? 'MXN') === 'USD' ? 'US$' : '$';
        $company = $purchaseOrder->requisition?->company;
    @endphp
    <table class="header"><tr>
        <td style="width:48%">@if(is_file($logoPath)) <img class="logo" src="{{ $logoPath }}" alt="TotalGas"> @else <strong style="font-size:17px;color:#188ae2">TOTALGAS</strong> @endif</td>
        <td style="width:52%"><h1 class="title">ORDEN DE COMPRA</h1><div class="folio">{{ $purchaseOrder->folio }}</div><div class="meta">Fecha de emisión: {{ ($purchaseOrder->issued_at ?? $purchaseOrder->created_at)?->format('d/m/Y') }}</div></td>
    </tr></table>

    <div class="section-title">Datos generales</div>
    <table class="info"><tr>
        <td><span class="label">Empresa compradora</span><span class="value">{{ $company?->legal_name ?? $company?->name ?? '—' }}</span><br><span class="label" style="margin-top:5px">RFC</span><span class="value">{{ $company?->rfc ?? '—' }}</span></td>
        <td><span class="label">Proveedor</span><span class="value">{{ $purchaseOrder->supplier?->company_name ?? '—' }}</span><br><span class="label" style="margin-top:5px">RFC</span><span class="value">{{ $purchaseOrder->supplier?->rfc ?? '—' }}</span><br><span class="label" style="margin-top:5px">Contacto</span><span class="value">{{ $purchaseOrder->supplier?->contact_person ?? '—' }}</span></td>
        <td><span class="label">Requisición origen</span><span class="value">{{ $purchaseOrder->requisition?->folio ?? '—' }}</span><br><span class="label" style="margin-top:5px">Condiciones de pago</span><span class="value">{{ $purchaseOrder->payment_terms ?? '—' }}</span><br><span class="label" style="margin-top:5px">Tiempo estimado de entrega</span><span class="value">{{ $purchaseOrder->estimated_delivery_days ? $purchaseOrder->estimated_delivery_days.' días' : '—' }}</span></td>
    </tr></table>

    <div class="section-title">Entrega</div>
    <table class="info"><tr>
        <td style="width:50%"><span class="label">Punto de entrega</span><span class="value">{{ $purchaseOrder->receivingLocation ? $purchaseOrder->receivingLocation->code.' · '.$purchaseOrder->receivingLocation->name : '—' }}</span></td>
        <td style="width:50%"><span class="label">Solicitante</span><span class="value">{{ $purchaseOrder->requisition?->requester?->name ?? $purchaseOrder->creator?->name ?? '—' }}</span></td>
    </tr></table>

    <div class="section-title">Partidas</div>
    <table class="items"><thead><tr><th style="width:5%">#</th><th>Descripción</th><th style="width:9%" class="center">Cantidad</th><th style="width:10%" class="center">Unidad</th><th style="width:13%" class="number">P. unitario</th><th style="width:9%" class="number">IVA</th><th style="width:14%" class="number">Importe</th></tr></thead>
        <tbody>@foreach($purchaseOrder->items as $index => $item) @php($rate = (float)$item->subtotal > 0 ? round(((float)$item->iva_amount / (float)$item->subtotal) * 100) : 0)<tr><td class="center">{{ $index + 1 }}</td><td><strong>{{ $item->description }}</strong>@if($item->requisitionItem?->costCenter)<br><span style="color:#64748b;font-size:7px">CC: {{ $item->requisitionItem->costCenter->code }} · {{ $item->requisitionItem->costCenter->name }}</span>@endif</td><td class="center">{{ number_format((float)$item->quantity, 2) }}</td><td class="center">{{ $item->requisitionItem?->unit ?? '—' }}</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$item->unit_price, 2) }}</td><td class="number">{{ $rate }}%</td><td class="number"><strong>{{ $currencySymbol }}{{ number_format((float)$item->total, 2) }}</strong></td></tr>@endforeach</tbody>
    </table>
    <table class="totals"><tr><td>Subtotal</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$purchaseOrder->subtotal, 2) }}</td></tr><tr><td>IVA</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$purchaseOrder->iva_amount, 2) }}</td></tr><tr class="grand"><td>TOTAL {{ $purchaseOrder->currency }}</td><td class="number">{{ $currencySymbol }}{{ number_format((float)$purchaseOrder->total, 2) }}</td></tr></table>
    @if($purchaseOrder->requisition?->description)<div class="section-title">Observaciones</div><div class="notes">{{ $purchaseOrder->requisition->description }}</div>@endif
    <table style="width:100%"><tr><td class="signature"><div class="signature-line"></div><strong>{{ $purchaseOrder->creator?->name ?? '—' }}</strong><br>Elaboró</td><td class="signature"><div class="signature-line"></div><strong>{{ $purchaseOrder->approver?->name ?? $purchaseOrder->assignedApprover?->name ?? '—' }}</strong><br>Autorizó</td></tr></table>
    <div class="footer">Documento generado por Portal de Proveedores TotalGas · {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
