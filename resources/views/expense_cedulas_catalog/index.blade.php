@extends('layouts.zircos')

@section('title', 'Cédulas de Gasto')
@section('page.title', 'Cédulas de Gasto')
@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Cédulas de Gasto</li>
@endsection

@section('content')
<div class="row">
    {{-- Panel izquierdo: Categorías --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ti ti-category me-1"></i> Categorías de Gasto</h5>
                <button type="button" class="btn btn-primary btn-sm" id="btnNewCategory">
                    <i class="ti ti-plus me-1"></i> Nueva categoría
                </button>
            </div>
            <div class="card-body">
                <input type="text" id="categoryFilter" class="form-control form-control-sm mb-3"
                    placeholder="Buscar por código o nombre...">
                <div id="categoryList" class="list-group"></div>
                <div id="categoryEmpty" class="text-muted text-center py-4 d-none">No hay categorías que coincidan con la búsqueda.</div>
            </div>
        </div>
    </div>

    {{-- Panel derecho: Cédulas --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0" id="cedulaPanelTitle"><i class="ti ti-list-details me-1"></i> Cédulas</h5>
                <button type="button" class="btn btn-primary btn-sm" id="btnNewCedula" disabled>
                    <i class="ti ti-plus me-1"></i> Nueva cédula
                </button>
            </div>
            <div class="card-body">
                <div id="cedulaEmptyState" class="text-muted text-center py-5">
                    <i class="ti ti-arrow-left me-1"></i> Selecciona una categoría para ver sus cédulas.
                </div>
                <div id="cedulaPanelBody" class="d-none">
                    <input type="text" id="cedulaFilter" class="form-control form-control-sm mb-3"
                        placeholder="Buscar cédula...">
                    <div id="cedulaList" class="list-group"></div>
                    <div id="cedulaEmpty" class="text-muted text-center py-4 d-none">Esta categoría no tiene cédulas.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Categoría --}}
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="categoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Nueva categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="category_id">
                    <div class="mb-3">
                        <label for="category_code" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="category_code" maxlength="3" class="form-control text-uppercase">
                        <div class="invalid-feedback" id="err-code"></div>
                    </div>
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="category_name" maxlength="200" class="form-control">
                        <div class="invalid-feedback" id="err-name"></div>
                    </div>
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Descripción</label>
                        <textarea id="category_description" class="form-control" rows="2"></textarea>
                        <div class="invalid-feedback" id="err-description"></div>
                    </div>
                    <div class="mb-0">
                        <label for="category_status" class="form-label">Estado <span class="text-danger">*</span></label>
                        <select id="category_status" class="form-select">
                            <option value="ACTIVO">Activo</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                        <div class="invalid-feedback" id="err-status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="categorySubmitBtn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Cédula --}}
<div class="modal fade" id="cedulaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cedulaForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="cedulaModalTitle">Nueva cédula</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cedula_id">
                    <input type="hidden" id="cedula_category_id">
                    <div class="mb-3">
                        <label class="form-label">Categoría</label>
                        <input type="text" id="cedula_category_label" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="cedula_name" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="cedula_name" maxlength="200" class="form-control">
                        <div class="invalid-feedback" id="err-cedula-name"></div>
                    </div>
                    <div class="mb-0">
                        <label for="cedula_status" class="form-label">Estado <span class="text-danger">*</span></label>
                        <select id="cedula_status" class="form-select">
                            <option value="ACTIVO">Activo</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                        <div class="invalid-feedback" id="err-cedula-status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="cedulaSubmitBtn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const urls = {
        categories: "{{ route('expense-cedulas.categories.data') }}",
        categoryStore: "{{ route('expense-cedulas.categories.store') }}",
        categoryUpdate: (id) => `{{ url('expense-cedulas/categories') }}/${id}`,
        categoryDestroy: (id) => `{{ url('expense-cedulas/categories') }}/${id}`,
        cedulas: (categoryId) => `{{ url('expense-cedulas/categories') }}/${categoryId}/cedulas`,
        cedulaStore: "{{ route('expense-cedulas.cedulas.store') }}",
        cedulaUpdate: (id) => `{{ url('expense-cedulas/cedulas') }}/${id}`,
        cedulaDestroy: (id) => `{{ url('expense-cedulas/cedulas') }}/${id}`,
    };

    let allCategories = @json($categories);
    let allCedulas = [];
    let selectedCategory = null;

    const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    const cedulaModal = new bootstrap.Modal(document.getElementById('cedulaModal'));

    function fetchJson(url, options = {}) {
        return fetch(url, {
            ...options,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        }).then(res => res.json().then(data => ({ status: res.status, data })));
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
    }

    function showErrors(errors, fieldMap) {
        Object.entries(errors).forEach(([field, messages]) => {
            const map = fieldMap[field];
            if (!map) return;
            const input = document.getElementById(map.input);
            const errEl = document.getElementById(map.error);
            if (input) input.classList.add('is-invalid');
            if (errEl) errEl.textContent = messages[0];
        });
    }

    function notifySuccess(message) {
        Swal.fire({ icon: 'success', title: '¡Listo!', text: message, timer: 2500, showConfirmButton: false });
    }

    function notifyError(message) {
        Swal.fire({ icon: 'error', title: 'No se pudo completar la operación', text: message || 'Ocurrió un error inesperado.' });
    }

    function statusBadge(status) {
        return status === 'ACTIVO'
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    // ── Categorías ───────────────────────────────────────────────
    function renderCategories() {
        const term = document.getElementById('categoryFilter').value.trim().toLowerCase();
        const list = document.getElementById('categoryList');
        const filtered = allCategories.filter(c =>
            c.code.toLowerCase().includes(term) || c.name.toLowerCase().includes(term)
        );

        document.getElementById('categoryEmpty').classList.toggle('d-none', filtered.length > 0);

        list.innerHTML = filtered.map(c => `
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${selectedCategory?.id === c.id ? 'active' : ''}"
                 data-id="${c.id}">
                <span class="js-select-category flex-grow-1" style="cursor:pointer">
                    <strong>${escapeHtml(c.code)}</strong> — ${escapeHtml(c.name)}
                    ${statusBadge(c.status)}
                    <span class="badge bg-light text-dark ms-1">${c.cedulas_count} cédula(s)</span>
                </span>
                <span class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-link p-1 js-edit-category" data-id="${c.id}" title="Editar"><i class="ti ti-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-link p-1 text-danger js-delete-category" data-id="${c.id}" title="Eliminar"><i class="ti ti-trash"></i></button>
                </span>
            </div>
        `).join('');
    }

    function loadCategories(selectId = null) {
        return fetchJson(urls.categories).then(({ data }) => {
            allCategories = data.data;
            renderCategories();

            if (selectId) {
                const found = allCategories.find(c => c.id === selectId);
                if (found) selectCategory(found);
                return;
            }

            if (selectedCategory) {
                const stillThere = allCategories.find(c => c.id === selectedCategory.id);
                if (stillThere) {
                    selectedCategory = stillThere;
                    renderCategories();
                } else {
                    clearCedulaPanel();
                }
            }
        });
    }

    function selectCategory(category) {
        selectedCategory = category;
        renderCategories();
        document.getElementById('cedulaPanelTitle').innerHTML =
            `<i class="ti ti-list-details me-1"></i> Cédulas de: ${escapeHtml(category.code)} ${escapeHtml(category.name)}`;
        document.getElementById('btnNewCedula').disabled = false;
        document.getElementById('cedulaEmptyState').classList.add('d-none');
        document.getElementById('cedulaPanelBody').classList.remove('d-none');
        loadCedulas(category.id);
    }

    function clearCedulaPanel() {
        selectedCategory = null;
        allCedulas = [];
        document.getElementById('cedulaPanelTitle').innerHTML = '<i class="ti ti-list-details me-1"></i> Cédulas';
        document.getElementById('btnNewCedula').disabled = true;
        document.getElementById('cedulaEmptyState').classList.remove('d-none');
        document.getElementById('cedulaPanelBody').classList.add('d-none');
    }

    document.getElementById('categoryFilter').addEventListener('input', renderCategories);

    document.getElementById('categoryList').addEventListener('click', function (e) {
        const editBtn = e.target.closest('.js-edit-category');
        const delBtn = e.target.closest('.js-delete-category');
        const item = e.target.closest('[data-id]');

        if (editBtn) { openCategoryModal(Number(editBtn.dataset.id)); return; }
        if (delBtn) { deleteCategory(Number(delBtn.dataset.id)); return; }
        if (item) {
            const cat = allCategories.find(c => c.id === Number(item.dataset.id));
            if (cat) selectCategory(cat);
        }
    });

    document.getElementById('btnNewCategory').addEventListener('click', () => openCategoryModal(null));

    function openCategoryModal(id) {
        const form = document.getElementById('categoryForm');
        clearErrors(form);
        form.reset();
        document.getElementById('category_id').value = '';
        document.getElementById('category_status').value = 'ACTIVO';

        if (id) {
            const cat = allCategories.find(c => c.id === id);
            document.getElementById('categoryModalTitle').textContent = 'Editar categoría';
            document.getElementById('category_id').value = cat.id;
            document.getElementById('category_code').value = cat.code;
            document.getElementById('category_name').value = cat.name;
            document.getElementById('category_description').value = cat.description ?? '';
            document.getElementById('category_status').value = cat.status;
        } else {
            document.getElementById('categoryModalTitle').textContent = 'Nueva categoría';
        }
        categoryModal.show();
    }

    document.getElementById('categoryForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        clearErrors(form);

        const id = document.getElementById('category_id').value;
        const payload = {
            code: document.getElementById('category_code').value,
            name: document.getElementById('category_name').value,
            description: document.getElementById('category_description').value,
            status: document.getElementById('category_status').value,
        };
        const url = id ? urls.categoryUpdate(id) : urls.categoryStore;
        const method = id ? 'PUT' : 'POST';
        const btn = document.getElementById('categorySubmitBtn');
        btn.disabled = true;

        fetchJson(url, { method, body: JSON.stringify(payload) })
            .then(({ status, data }) => {
                if (status === 200 || status === 201) {
                    categoryModal.hide();
                    notifySuccess(data.message);
                    loadCategories(data.data.id);
                } else if (status === 422) {
                    showErrors(data.errors, {
                        code: { input: 'category_code', error: 'err-code' },
                        name: { input: 'category_name', error: 'err-name' },
                        description: { input: 'category_description', error: 'err-description' },
                        status: { input: 'category_status', error: 'err-status' },
                    });
                } else {
                    notifyError(data.error);
                }
            })
            .catch(() => notifyError())
            .finally(() => btn.disabled = false);
    });

    function deleteCategory(id) {
        const cat = allCategories.find(c => c.id === id);
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la categoría?',
            text: `¿Eliminar "${cat.code} - ${cat.name}"? Esta acción no se puede deshacer.`,
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
        }).then(result => {
            if (!result.isConfirmed) return;
            fetchJson(urls.categoryDestroy(id), { method: 'DELETE' })
                .then(({ status, data }) => {
                    if (status === 200) {
                        notifySuccess(data.message);
                        if (selectedCategory?.id === id) clearCedulaPanel();
                        loadCategories();
                    } else {
                        notifyError(data.error);
                    }
                });
        });
    }

    // ── Cédulas ──────────────────────────────────────────────────
    function renderCedulas() {
        const term = document.getElementById('cedulaFilter').value.trim().toLowerCase();
        const list = document.getElementById('cedulaList');
        const filtered = allCedulas.filter(c => c.name.toLowerCase().includes(term));

        document.getElementById('cedulaEmpty').classList.toggle('d-none', filtered.length > 0);

        list.innerHTML = filtered.map(c => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span>${escapeHtml(c.name)} ${statusBadge(c.status)}</span>
                <span class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-link p-1 js-edit-cedula" data-id="${c.id}" title="Editar"><i class="ti ti-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-link p-1 text-danger js-delete-cedula" data-id="${c.id}" title="Eliminar"><i class="ti ti-trash"></i></button>
                </span>
            </div>
        `).join('');
    }

    function loadCedulas(categoryId) {
        return fetchJson(urls.cedulas(categoryId)).then(({ data }) => {
            allCedulas = data.data;
            document.getElementById('cedulaFilter').value = '';
            renderCedulas();
        });
    }

    document.getElementById('cedulaFilter').addEventListener('input', renderCedulas);

    document.getElementById('cedulaList').addEventListener('click', function (e) {
        const editBtn = e.target.closest('.js-edit-cedula');
        const delBtn = e.target.closest('.js-delete-cedula');
        if (editBtn) { openCedulaModal(Number(editBtn.dataset.id)); return; }
        if (delBtn) { deleteCedula(Number(delBtn.dataset.id)); return; }
    });

    document.getElementById('btnNewCedula').addEventListener('click', () => openCedulaModal(null));

    function openCedulaModal(id) {
        if (!selectedCategory) return;

        const form = document.getElementById('cedulaForm');
        clearErrors(form);
        form.reset();
        document.getElementById('cedula_id').value = '';
        document.getElementById('cedula_category_id').value = selectedCategory.id;
        document.getElementById('cedula_category_label').value = `${selectedCategory.code} - ${selectedCategory.name}`;
        document.getElementById('cedula_status').value = 'ACTIVO';

        if (id) {
            const cedula = allCedulas.find(c => c.id === id);
            document.getElementById('cedulaModalTitle').textContent = 'Editar cédula';
            document.getElementById('cedula_id').value = cedula.id;
            document.getElementById('cedula_name').value = cedula.name;
            document.getElementById('cedula_status').value = cedula.status;
        } else {
            document.getElementById('cedulaModalTitle').textContent = 'Nueva cédula';
        }
        cedulaModal.show();
    }

    document.getElementById('cedulaForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        clearErrors(form);

        const id = document.getElementById('cedula_id').value;
        const payload = {
            expense_category_id: document.getElementById('cedula_category_id').value,
            name: document.getElementById('cedula_name').value,
            status: document.getElementById('cedula_status').value,
        };
        const url = id ? urls.cedulaUpdate(id) : urls.cedulaStore;
        const method = id ? 'PUT' : 'POST';
        const btn = document.getElementById('cedulaSubmitBtn');
        btn.disabled = true;

        fetchJson(url, { method, body: JSON.stringify(payload) })
            .then(({ status, data }) => {
                if (status === 200 || status === 201) {
                    cedulaModal.hide();
                    notifySuccess(data.message);
                    loadCedulas(selectedCategory.id);
                    loadCategories(selectedCategory.id);
                } else if (status === 422) {
                    showErrors(data.errors, {
                        name: { input: 'cedula_name', error: 'err-cedula-name' },
                        status: { input: 'cedula_status', error: 'err-cedula-status' },
                    });
                } else {
                    notifyError(data.error);
                }
            })
            .catch(() => notifyError())
            .finally(() => btn.disabled = false);
    });

    function deleteCedula(id) {
        const cedula = allCedulas.find(c => c.id === id);
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la cédula?',
            text: `¿Eliminar "${cedula.name}"? Esta acción no se puede deshacer.`,
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
        }).then(result => {
            if (!result.isConfirmed) return;
            fetchJson(urls.cedulaDestroy(id), { method: 'DELETE' })
                .then(({ status, data }) => {
                    if (status === 200) {
                        notifySuccess(data.message);
                        loadCedulas(selectedCategory.id);
                        loadCategories(selectedCategory.id);
                    } else {
                        notifyError(data.error);
                    }
                });
        });
    }

    renderCategories();
});
</script>
@endpush
