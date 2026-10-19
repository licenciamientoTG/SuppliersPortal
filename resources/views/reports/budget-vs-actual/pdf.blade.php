<html>
<head>
<style>
    body { font-family: DejaVu Sans; font-size: 9px; color: #24364b; }
    h1 { color: #188ae2; font-size: 16px; margin: 0 0 4px; }
    .header-line { font-size: 8px; color: #637386; margin: 0 0 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    thead tr { background: #f1f4f7; }
    th, td { border: 1px solid #e2e9f0; padding: 4px 5px; text-align: left; }
    td.amount, th.amount { text-align: right; }
    td.center { text-align: center; }
    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
    .rojo { background: #d9463f; }
    .amarillo { background: #e0a72a; }
    .verde { background: #2fa360; }
    .empty { text-align: center; color: #9aa5b1; padding: 14px; }
</style>
</head>
<body>
    <h1>RP-01 · Resumen ejecutivo por centro de costo</h1>
    @foreach($header as $line)
        <p class="header-line">{{ $line }}</p>
    @endforeach

    @if($rows->isEmpty())
        <p class="empty">No hay renglones de presupuesto aprobado para estos filtros.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Centro de costo</th>
                    <th>Empresa</th>
                    <th>Responsable</th>
                    <th class="amount">Vigente</th>
                    <th class="amount">Consumido</th>
                    <th class="amount">Disponible</th>
                    <th class="amount">Avance</th>
                    <th class="center">Semáforo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['cost_center_code'] }} · {{ $row['cost_center_name'] }}</td>
                        <td>{{ $row['company_name'] }}</td>
                        <td>{{ $row['responsible_name'] ?? '—' }}</td>
                        <td class="amount">${{ number_format($row['current_budget'], 2) }}</td>
                        <td class="amount">${{ number_format($row['consumed_total'], 2) }}</td>
                        <td class="amount">${{ number_format($row['available'], 2) }}</td>
                        <td class="amount">{{ $row['progress_pct'] === null ? '—' : number_format($row['progress_pct'] * 100, 1).'%' }}</td>
                        <td class="center"><span class="dot {{ strtolower($row['traffic_light']) }}"></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
