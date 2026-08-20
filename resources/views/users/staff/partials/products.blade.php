<div class="modal-header">
    <h5 class="modal-title">
        Productos/Servicios de {{ $user->full_name ?? $user->name }}
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
    <p class="text-muted small mb-3">
        Productos y servicios activos a los que este usuario tiene acceso, según su departamento y perfiles presupuestales asignados.
    </p>

    @if ($products->isEmpty())
        <div class="text-center text-muted py-4">
            <i class="ti ti-package-off fs-2 d-block mb-2"></i>
            Este usuario no tiene productos o servicios accesibles.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Cuenta / Subcuenta</th>
                        <th>Unidad</th>
                        <th class="text-end">Precio Est.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $p)
                        <tr>
                            <td><span class="badge bg-secondary">{{ $p->code }}</span></td>
                            <td>{{ $p->short_name ?? $p->technical_description }}</td>
                            <td>
                                <span class="badge bg-{{ $p->product_type === 'SERVICIO' ? 'info' : 'primary' }}">
                                    {{ $p->product_type }}
                                </span>
                            </td>
                            <td>
                                @forelse ($p->subaccounts as $subaccount)
                                    @if ($subaccount->account)
                                        {{ $subaccount->account->name }}-{{ $subaccount->name }}@if (! $loop->last)<br>@endif
                                    @endif
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $p->unit_of_measure }}</td>
                            <td class="text-end">{{ number_format((float) $p->estimated_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
</div>
