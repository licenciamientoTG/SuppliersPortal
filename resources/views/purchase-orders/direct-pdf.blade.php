<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('purchase-orders.partials.pdf-styles')
</head>
<body>
    @php
        $currencySymbol = ($directPurchaseOrder->currency ?? 'MXN') === 'USD' ? 'US$' : '$';
        $supplier = $directPurchaseOrder->supplier;
        $receivingLocation = $directPurchaseOrder->receivingLocation;
        $supplierAddress = collect([
            $supplier?->address,
            $supplier?->postal_code ? 'C.P. '.$supplier->postal_code : null,
        ])->filter()->implode(' - ');
        $supplierContact = collect([
            $supplier?->contact_person,
            $supplier?->contact_phone ?? $supplier?->phone_number,
            $supplier?->email,
        ])->filter()->implode(' · ');
        $deliveryAddress = collect([
            $receivingLocation?->address,
            $receivingLocation?->city,
            $receivingLocation?->state,
            $receivingLocation?->postal_code ? 'C.P. '.$receivingLocation->postal_code : null,
        ])->filter()->implode(', ');
        $deliveryContact = collect([
            $receivingLocation?->manager_name ? 'Recibe: '.$receivingLocation->manager_name : null,
            $receivingLocation?->phone,
            $receivingLocation?->email,
        ])->filter()->implode(' · ');
        $signatures = collect([
            ['label' => 'Elaboró', 'name' => $directPurchaseOrder->creator?->name, 'role' => 'Solicitante'],
            ['label' => 'Aceptó', 'name' => $supplier?->company_name, 'role' => 'Proveedor'],
            ['label' => 'Autorizó', 'name' => $directPurchaseOrder->approver?->name, 'role' => $directPurchaseOrder->authorizerRole?->name ?? 'Autorizador de la OCD'],
            $directPurchaseOrder->receiver ? ['label' => 'Recibió', 'name' => $directPurchaseOrder->receiver->name, 'role' => 'Responsable de recepción'] : null,
        ])->filter(fn ($signature) => filled($signature['name'] ?? null))->values();
    @endphp

    @include('purchase-orders.partials.pdf-letterhead', [
        'title' => 'Orden de compra directa',
        'folio' => $directPurchaseOrder->folio,
    ])

    <table class="kpis"><tr>
        <td><div class="kpi"><div class="k">Fecha de emisión</div><div class="v">{{ ($directPurchaseOrder->issued_at ?? $directPurchaseOrder->created_at)?->format('d/m/Y') ?? '—' }}</div></div></td>
        <td><div class="kpi kpi-sky"><div class="k">Centro de costo</div><div class="v">{{ $directPurchaseOrder->primaryCostCenterLabel() ?: '—' }}</div></div></td>
        <td><div class="kpi kpi-green"><div class="k">Condiciones de pago</div><div class="v">{{ $directPurchaseOrder->payment_terms ?? '—' }}</div></div></td>
        <td class="last"><div class="kpi kpi-lime"><div class="k">Entrega estimada</div><div class="v">{{ $directPurchaseOrder->estimated_delivery_days ? $directPurchaseOrder->estimated_delivery_days.' días' : '—' }}</div></div></td>
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
            <div class="party-n">{{ $receivingLocation ? collect([$receivingLocation->code, $receivingLocation->name])->filter()->implode(' · ') : '—' }}</div>
            @if($deliveryAddress)<div class="sub">{{ $deliveryAddress }}</div>@endif
            @if($deliveryContact)<div class="sub">{{ $deliveryContact }}</div>@endif
            <div class="sub">Solicitante: {{ $directPurchaseOrder->creator?->name ?? '—' }}</div>
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
        @foreach($directPurchaseOrder->items as $item)
            <tr>
                <td class="idx">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                <td>
                    <b>{{ $item->description }}</b>
                    @if($item->costCenter)<br><span class="muted">{{ $item->costCenter->code }} · {{ $item->costCenter->name }}</span>@endif
                </td>
                <td class="ctr">{{ number_format((float) $item->quantity, 2) }}</td>
                <td class="ctr">{{ $item->unit_of_measure ?? '—' }}</td>
                <td class="r">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="r">{{ rtrim(rtrim(number_format((float) $item->iva_rate, 2), '0'), '.') }}%</td>
                <td class="r"><b>{{ $currencySymbol }}{{ number_format((float) $item->total, 2) }}</b></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="summary"><tr>
        <td style="width:56%;padding-right:24px">
            @if($directPurchaseOrder->justification)
                <div class="obs-t">Justificación</div>
                <div class="obs">{{ $directPurchaseOrder->justification }}</div>
            @endif
        </td>
        <td style="width:44%">
            <table class="st">
                <tr><td class="sub">Subtotal</td><td class="r">{{ $currencySymbol }}{{ number_format((float) $directPurchaseOrder->subtotal, 2) }}</td></tr>
                <tr><td class="sub">IVA</td><td class="r">{{ $currencySymbol }}{{ number_format((float) $directPurchaseOrder->iva_amount, 2) }}</td></tr>
            </table>
            <div class="total-k">Total {{ $directPurchaseOrder->currency ?? 'MXN' }}</div>
            <div class="total-v">{{ $currencySymbol }}{{ number_format((float) $directPurchaseOrder->total, 2) }}</div>
            <div class="total-u"></div>
        </td>
    </tr></table>

    @if($signatures->isNotEmpty())
        <table class="signs"><tr>
            @foreach($signatures as $signature)
                <td style="width:{{ 100 / $signatures->count() }}%"><div class="sbox"><div class="k">{{ $signature['label'] }}</div></div><div class="sname">{{ $signature['name'] }}</div><div class="srole">{{ $signature['role'] }}</div></td>
            @endforeach
        </tr></table>
    @endif
</body>
</html>
