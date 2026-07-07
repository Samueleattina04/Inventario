@extends('layouts.app')

@section('title', 'Seleziona Posizione')

@section('content')
@if($demoMode)
<div class="alert alert-warning text-center fw-bold mb-3 border-warning border-2">
    <i class="bi bi-cone-striped me-2"></i>MODALITÀ DEMO — Le scansioni NON verranno salvate nell'inventario reale
</div>
@endif
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-geo-alt-fill me-2"></i>Seleziona Magazzino e Area
                </h5>
            </div>
            <div class="card-body p-3 p-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('location') }}" id="locationForm">
                    @csrf

                    <div class="mb-3">
                        <label for="warehouse_id" class="form-label fw-semibold fs-5">
                            <i class="bi bi-building me-1"></i>Magazzino
                        </label>
                        <select
                            id="warehouse_id"
                            name="warehouse_id"
                            class="form-select form-select-lg @error('warehouse_id') is-invalid @enderror"
                            onchange="updateAreas(this.value)"
                        >
                            <option value="">— Seleziona magazzino —</option>
                            @foreach($warehouses as $warehouse)
                                <option
                                    value="{{ $warehouse->id }}"
                                    data-areas="{{ json_encode($warehouse->activeAreas->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->code])) }}"
                                    {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}
                                >
                                    {{ $warehouse->name }} ({{ $warehouse->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4" id="areaSection">
                        <label for="area_id" class="form-label fw-semibold fs-5">
                            <i class="bi bi-layers me-1"></i>Area
                        </label>
                        <select
                            id="area_id"
                            name="area_id"
                            class="form-select form-select-lg @error('area_id') is-invalid @enderror"
                            disabled
                        >
                            <option value="">— Prima seleziona un magazzino —</option>
                        </select>
                        @error('area_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div id="areaAlert" class="alert alert-warning mt-2 d-none">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Seleziona un'area.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                            <i class="bi bi-play-fill me-2"></i>Inizia Inventario
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if(session('warehouse_name'))
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body py-2 px-3">
                    <small class="text-muted">
                        <i class="bi bi-clock-history me-1"></i>Ultima posizione:
                        <strong>{{ session('warehouse_name') }} / {{ session('area_name') }}</strong>
                    </small>
                    <a href="{{ route('scan') }}" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="bi bi-arrow-right-circle"></i> Continua
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
const warehouseSelect = document.getElementById('warehouse_id');
const areaSelect      = document.getElementById('area_id');
const submitBtn       = document.getElementById('submitBtn');
const areaAlert       = document.getElementById('areaAlert');
let warehouseHasAreas = false;

function updateAreas(warehouseId) {
    areaSelect.innerHTML = '';
    areaSelect.disabled  = true;
    areaAlert.classList.add('d-none');
    warehouseHasAreas    = false;

    if (!warehouseId) {
        areaSelect.innerHTML = '<option value="">— Prima seleziona un magazzino —</option>';
        submitBtn.disabled   = true;
        return;
    }

    const option = warehouseSelect.querySelector(`option[value="${warehouseId}"]`);
    if (!option) return;

    const areas = JSON.parse(option.dataset.areas || '[]');

    if (areas.length === 0) {
        // Magazzino senza aree: si può procedere subito
        areaSelect.innerHTML = '<option value="">Nessuna area</option>';
        submitBtn.disabled   = false;
        return;
    }

    // Magazzino con aree: area obbligatoria
    warehouseHasAreas    = true;
    areaSelect.innerHTML = '<option value="">— Seleziona area —</option>';
    areas.forEach(area => {
        const opt       = document.createElement('option');
        opt.value       = area.id;
        opt.textContent = area.name;
        areaSelect.appendChild(opt);
    });
    areaSelect.disabled  = false;
    submitBtn.disabled   = true;

    // Ripristina selezione precedente
    const oldAreaId = '{{ old('area_id') }}';
    if (oldAreaId) {
        areaSelect.value = oldAreaId;
        if (areaSelect.value) submitBtn.disabled = false;
    }
}

areaSelect.addEventListener('change', function() {
    areaAlert.classList.add('d-none');
    submitBtn.disabled = warehouseHasAreas && !this.value;
});

document.getElementById('locationForm').addEventListener('submit', function(e) {
    if (warehouseHasAreas && !areaSelect.value) {
        e.preventDefault();
        areaAlert.classList.remove('d-none');
        areaSelect.focus();
    }
});

window.addEventListener('DOMContentLoaded', function() {
    if (warehouseSelect.value) updateAreas(warehouseSelect.value);
});
</script>
@endsection
