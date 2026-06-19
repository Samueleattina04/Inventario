@extends('layouts.admin')

@section('title', 'Magazzini e Aree')
@section('breadcrumb')
    <li class="breadcrumb-item active">Magazzini/Aree</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Magazzini e Aree</h4>
    <a href="{{ route('admin.warehouses.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Nuovo Magazzino
    </a>
</div>

@forelse($warehouses as $warehouse)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
        <div>
            <span class="fw-bold fs-5">{{ $warehouse->name }}</span>
            <span class="badge bg-secondary ms-2">{{ $warehouse->code }}</span>
            @if(!$warehouse->active)
                <span class="badge bg-warning text-dark ms-1">Disattivato</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-success btn-add-area"
                    data-warehouse-id="{{ $warehouse->id }}"
                    data-warehouse-name="{{ $warehouse->name }}"
                    data-store-url="{{ route('admin.warehouses.areas.store', $warehouse) }}">
                <i class="bi bi-plus me-1"></i>Area
            </button>
            <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" action="{{ route('admin.warehouses.destroy', $warehouse) }}" class="d-inline"
                  onsubmit="return confirm('Eliminare il magazzino {{ addslashes($warehouse->name) }} e tutte le sue aree?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        @if($warehouse->areas->count())
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Area</th><th>Codice</th><th>Calcolatore Peso</th><th>Stato</th><th class="text-end">Azioni</th>
            </tr></thead>
            <tbody>
                @foreach($warehouse->areas as $area)
                <tr>
                    <td class="fw-semibold">{{ $area->name }}</td>
                    <td><code>{{ $area->code }}</code></td>
                    <td>
                        @if($area->has_weight_calculator)
                            <span class="badge bg-info text-dark"><i class="bi bi-calculator me-1"></i>Sì</span>
                        @else
                            <span class="text-muted small">No</span>
                        @endif
                    </td>
                    <td>
                        @if($area->active)
                            <span class="badge bg-success">Attiva</span>
                        @else
                            <span class="badge bg-secondary">Disattiva</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary me-1 btn-edit-area"
                                data-area-id="{{ $area->id }}"
                                data-area-name="{{ $area->name }}"
                                data-area-code="{{ $area->code }}"
                                data-area-hwc="{{ $area->has_weight_calculator ? '1' : '0' }}"
                                data-area-active="{{ $area->active ? '1' : '0' }}"
                                data-update-url="{{ route('admin.warehouses.areas.update', [$warehouse, $area]) }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.warehouses.areas.destroy', [$warehouse, $area]) }}"
                              class="d-inline" onsubmit="return confirm('Eliminare area {{ addslashes($area->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-center text-muted py-3 mb-0">
            Nessuna area.
            <a href="#" class="btn-add-area"
               data-warehouse-id="{{ $warehouse->id }}"
               data-warehouse-name="{{ $warehouse->name }}"
               data-store-url="{{ route('admin.warehouses.areas.store', $warehouse) }}">
               Aggiungi la prima area
            </a>.
        </p>
        @endif
    </div>
</div>
@empty
<div class="text-center py-5 text-muted">
    <i class="bi bi-building display-4 d-block mb-3"></i>
    Nessun magazzino. <a href="{{ route('admin.warehouses.create') }}">Crea il primo magazzino</a>.
</div>
@endforelse

{{-- Single Add Area Modal --}}
<div class="modal fade" id="modal-add-area" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="form-add-area" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Aggiungi Area — <span id="add-area-warehouse-name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome area <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Es. Area 1, Scaffale Nord...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Codice <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required placeholder="Es. A1, SN..." style="text-transform:uppercase">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="has_weight_calculator" value="1" id="add-hwc">
                        <label class="form-check-label" for="add-hwc">
                            <i class="bi bi-calculator me-1"></i>Abilita calcolatore peso→pezzi
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="add-active" checked>
                        <label class="form-check-label" for="add-active">Area attiva</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Aggiungi</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Single Edit Area Modal --}}
<div class="modal fade" id="modal-edit-area" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="form-edit-area" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Modifica Area</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome area <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit-area-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Codice <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="edit-area-code" class="form-control" required style="text-transform:uppercase">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="has_weight_calculator" value="1" id="edit-hwc">
                        <label class="form-check-label" for="edit-hwc">
                            <i class="bi bi-calculator me-1"></i>Calcolatore peso→pezzi
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="edit-active">
                        <label class="form-check-label" for="edit-active">Area attiva</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('.btn-add-area').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('add-area-warehouse-name').textContent = this.dataset.warehouseName;
        document.getElementById('form-add-area').action = this.dataset.storeUrl;
        document.querySelector('#form-add-area input[name="name"]').value = '';
        document.querySelector('#form-add-area input[name="code"]').value = '';
        document.getElementById('add-hwc').checked = false;
        document.getElementById('add-active').checked = true;
        new bootstrap.Modal(document.getElementById('modal-add-area')).show();
    });
});

document.querySelectorAll('.btn-edit-area').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('form-edit-area').action = this.dataset.updateUrl;
        document.getElementById('edit-area-name').value = this.dataset.areaName;
        document.getElementById('edit-area-code').value = this.dataset.areaCode;
        document.getElementById('edit-hwc').checked = this.dataset.areaHwc === '1';
        document.getElementById('edit-active').checked = this.dataset.areaActive === '1';
        new bootstrap.Modal(document.getElementById('modal-edit-area')).show();
    });
});
</script>
@endsection
