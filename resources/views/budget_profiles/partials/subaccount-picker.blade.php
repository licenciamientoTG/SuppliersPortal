@php
    $selectedSubaccountIds = collect($selectedSubaccountIds ?? [])->map(fn ($id) => (int) $id)->all();
    $pickerId = $pickerId ?? 'subaccountPicker'.uniqid();
    $groupedSubaccounts = $subaccounts->groupBy(fn ($subaccount) => $subaccount->account?->id ?? 0);
@endphp

<div class="bp-subaccount-picker js-subaccount-picker" id="{{ $pickerId }}">
    <div class="bp-picker-toolbar">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="ti ti-search"></i></span>
            <input type="search" class="form-control js-subaccount-search" placeholder="Buscar cuenta o subcuenta">
        </div>
        <span class="bp-picker-count">
            <strong class="js-subaccount-count">{{ count($selectedSubaccountIds) }}</strong> seleccionadas
        </span>
    </div>

    <div class="bp-picker-list">
        @foreach ($groupedSubaccounts as $accountId => $accountSubaccounts)
            @php
                $account = $accountSubaccounts->first()->account;
                $groupKey = $pickerId.'Group'.$accountId;
                $accountSearch = Str::lower(trim(($account?->code ?? '').' '.($account?->name ?? 'Sin cuenta')));
            @endphp

            <section class="bp-picker-group js-subaccount-group" data-search="{{ $accountSearch }}">
                <div class="bp-picker-group-head">
                    <button class="bp-picker-group-title collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $groupKey }}" aria-expanded="false" aria-controls="{{ $groupKey }}">
                        <i class="ti ti-chevron-down"></i>
                        <span>{{ $account?->code }} {{ $account?->name ?? 'Sin cuenta' }}</span>
                    </button>
                    <div class="bp-picker-actions">
                        <button type="button" class="btn btn-link btn-sm js-select-group">Todo</button>
                        <button type="button" class="btn btn-link btn-sm text-muted js-clear-group">Limpiar</button>
                    </div>
                </div>

                <div class="collapse" id="{{ $groupKey }}">
                    <div class="bp-picker-options">
                        @foreach ($accountSubaccounts as $subaccount)
                            @php
                                $optionSearch = Str::lower(trim(($account?->code ?? '').' '.($account?->name ?? '').' '.$subaccount->code.' '.$subaccount->name));
                            @endphp

                            <label class="bp-picker-option js-subaccount-option" data-search="{{ $optionSearch }}">
                                <input
                                    type="checkbox"
                                    name="subaccount_ids[]"
                                    value="{{ $subaccount->id }}"
                                    @checked(in_array((int) $subaccount->id, $selectedSubaccountIds, true))
                                >
                                <span>
                                    <strong>{{ $subaccount->code }}</strong>
                                    {{ $subaccount->name }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach

        <div class="bp-picker-empty">
            <i class="ti ti-search d-block fs-4 mb-1"></i>
            Sin subcuentas visibles.
        </div>
    </div>
</div>
