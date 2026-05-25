@php $currentPhotoUrl = $employee->photo ? Storage::url($employee->photo) : null; @endphp

<div class="modal-header border-bottom-0 pb-1">
    <div>
        <h5 class="modal-title fw-semibold">
            <i class="ti ti-camera me-2 text-muted fs-18"></i>
            Fotografía del empleado
        </h5>
        <p class="text-muted mb-0 mt-1" style="font-size:12px;">
            {{ $employee->full_name }}
        </p>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<form id="photoForm"
      action="{{ route('employees.upload-photo', $employee->id) }}"
      method="POST"
      enctype="multipart/form-data">
    @csrf

    <div class="modal-body pt-2">

        <div class="mb-3">
            <div id="photoDropZone"
                 class="border border-dashed rounded d-flex flex-column align-items-center justify-content-center p-3"
                 style="cursor:pointer;min-height:140px;border-style:dashed!important;">
                <img id="photoPreview"
                     src="{{ $currentPhotoUrl ?? asset('images/users/avatar-1.jpg') }}"
                     class="rounded-circle mb-2{{ $currentPhotoUrl ? '' : ' d-none' }}"
                     style="width:80px;height:80px;object-fit:cover;"
                     alt="Preview">
                <i id="photoDropIcon" class="ti ti-photo fs-32 text-muted{{ $currentPhotoUrl ? ' d-none' : '' }}"></i>
                <span class="text-muted mt-1" style="font-size:12px;">Arrastra la foto aquí o haz clic</span>
                <span class="text-muted" style="font-size:11px;">JPG, PNG o WEBP · Máx. 3 MB</span>
                <input type="file" name="photo" id="photoInput"
                       accept="image/png,image/jpeg,image/webp"
                       class="d-none">
            </div>
            <div class="invalid-feedback d-block" id="err-photo"></div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <i class="ti ti-x me-1"></i>Cancelar
        </button>
        <button type="submit" class="btn btn-primary btn-sm" id="photoSubmitBtn">
            <i class="ti ti-device-floppy me-1"></i>Guardar foto
        </button>
    </div>
</form>

<script>
(function () {
    const dropZone = document.getElementById('photoDropZone');
    const fileInput = document.getElementById('photoInput');
    const preview   = document.getElementById('photoPreview');
    const icon      = document.getElementById('photoDropIcon');

    function showPreview(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            icon.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    }

    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => showPreview(fileInput.files[0]));
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-primary'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-primary'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('border-primary');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            showPreview(file);
        }
    });
})();
</script>
