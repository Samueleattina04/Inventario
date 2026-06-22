@extends('layouts.admin')

@section('title', 'Modifica Registrazione')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventario</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.show', $record) }}">#{{ $record->id }}</a></li>
    <li class="breadcrumb-item active">Modifica</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-warning"></i>Modifica Registrazione #{{ $record->id }}</h4>
    <a href="{{ route('admin.inventory.show', $record) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Annulla
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger mb-3">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.inventory.update', $record) }}">
    @csrf @method('PUT')

    <div class="row g-4">

        {{-- Dati articolo --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-box-seam me-2"></i>Dati Articolo
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Codice Articolo *</label>
                        <input type="text" name="article_code" class="form-control @error('article_code') is-invalid @enderror"
                               value="{{ old('article_code', $record->article_code) }}" required>
                        @error('article_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descrizione</label>
                        <input type="text" name="description" class="form-control"
                               value="{{ old('description', $record->description) }}">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">U.M.</label>
                            <input type="text" name="um" class="form-control"
                                   value="{{ old('um', $record->um) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Quantità *</label>
                            <input type="number" name="quantity" id="edit-quantity" step="any" min="0"
                                   class="form-control @error('quantity') is-invalid @enderror"
                                   value="{{ old('quantity', $record->quantity) }}" required>
                            @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Lotto</label>
                            <input type="text" name="lot" class="form-control"
                                   value="{{ old('lot', $record->lot) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Scadenza</label>
                            <input type="date" name="expiry_date" class="form-control"
                                   value="{{ old('expiry_date', $record->expiry_date?->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $record->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Magazzino / Area + Info --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-building me-2"></i>Magazzino e Area
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Magazzino *</label>
                        <select name="warehouse_id" id="edit-warehouse" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                            <option value="">— Seleziona magazzino —</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}"
                                    {{ old('warehouse_id', $record->warehouse_id) == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Area</label>
                        <select name="area_id" id="edit-area" class="form-select">
                            <option value="">— Nessuna area —</option>
                            @foreach($warehouses as $wh)
                                @foreach($wh->activeAreas as $area)
                                    <option value="{{ $area->id }}"
                                        data-warehouse="{{ $wh->id }}"
                                        {{ old('area_id', $record->area_id) == $area->id ? 'selected' : '' }}>
                                        {{ $area->name }} ({{ $area->code }})
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Info Registrazione
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th class="text-muted" style="width:40%">Operatore</th>
                            <td>{{ $record->user?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Data/Ora</th>
                            <td>{{ $record->created_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">DB Provenienza</th>
                            <td>
                                @if($record->db_source === 'not_found')
                                    <span class="badge bg-danger me-2">Non trovato</span>
                                    <small class="text-muted d-block mt-1">Puoi assegnare manualmente un DB:</small>
                                    <select name="db_source" class="form-select form-select-sm mt-1">
                                        <option value="not_found" {{ old('db_source', $record->db_source) === 'not_found' ? 'selected' : '' }}>
                                            — Non assegnato —
                                        </option>
                                        <option value="sqlsrv" {{ old('db_source', $record->db_source) === 'sqlsrv' ? 'selected' : '' }}>
                                            SQL Server
                                        </option>
                                        <option value="access" {{ old('db_source', $record->db_source) === 'access' ? 'selected' : '' }}>
                                            Access
                                        </option>
                                    </select>
                                @else
                                    {{ $record->db_source_label }}
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Calcolatore peso --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background:#f0f9ff; border: 1px solid #0dcaf0 !important;">
                <div class="card-header fw-semibold" style="background:#e0f4fc;">
                    <i class="bi bi-calculator me-2"></i>Calcolatore Peso → Quantità
                    <small class="text-muted fw-normal ms-2">Modifica i valori per ricalcolare automaticamente la quantità</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Pezzi campione</label>
                            <input type="text" inputmode="numeric" name="sample_count" id="edit-sample-count"
                                   class="form-control text-center" placeholder="N."
                                   value="{{ old('sample_count', $record->sample_count) }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Peso campione (kg)</label>
                            <input type="number" name="sample_weight" id="edit-sample-weight"
                                   class="form-control text-center" step="0.001" min="0.001" placeholder="kg"
                                   value="{{ old('sample_weight', $record->sample_weight) }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Peso totale (kg)</label>
                            <input type="number" name="total_weight" id="edit-total-weight"
                                   class="form-control text-center" step="0.001" min="0" placeholder="kg"
                                   value="{{ old('total_weight', $record->total_weight) }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Tara (kg) <span class="text-muted fw-normal">(opzionale)</span></label>
                            <input type="number" name="tare" id="edit-tare"
                                   class="form-control text-center" step="0.001" min="0" placeholder="0"
                                   value="{{ old('tare', $record->tare ?? 0) }}">
                        </div>
                        <div class="col-12 col-md-3 d-flex flex-column justify-content-end">
                            <button type="button" class="btn btn-info text-white fw-bold" onclick="editRecalc()">
                                <i class="bi bi-arrow-repeat me-1"></i>Ricalcola Quantità
                            </button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Formula: ((Peso totale − Tara) ÷ Peso campione) × Pezzi campione
                    </small>
                </div>
            </div>
        </div>

    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-warning fw-bold">
            <i class="bi bi-floppy-fill me-2"></i>Salva Modifiche
        </button>
        <a href="{{ route('admin.inventory.show', $record) }}" class="btn btn-outline-secondary">
            Annulla
        </a>
    </div>
</form>
@endsection

@section('scripts')
<script>
// Cascading warehouse → area dropdown
const warehouseAreas = @json($warehouses->mapWithKeys(fn($wh) => [
    $wh->id => $wh->activeAreas->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->code])->values()
]));

const warehouseSelect = document.getElementById('edit-warehouse');
const areaSelect      = document.getElementById('edit-area');
const currentAreaId   = {{ old('area_id', $record->area_id) ?? 'null' }};

function updateAreas() {
    const whId  = parseInt(warehouseSelect.value);
    const areas = warehouseAreas[whId] || [];

    areaSelect.innerHTML = '<option value="">— Nessuna area —</option>';
    areas.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.name + ' (' + a.code + ')';
        if (a.id === currentAreaId) opt.selected = true;
        areaSelect.appendChild(opt);
    });
}

warehouseSelect.addEventListener('change', function() {
    // On manual change, don't pre-select any area
    const whId  = parseInt(this.value);
    const areas = warehouseAreas[whId] || [];
    areaSelect.innerHTML = '<option value="">— Nessuna area —</option>';
    areas.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.name + ' (' + a.code + ')';
        areaSelect.appendChild(opt);
    });
});

// Init on page load (keep current area selected)
updateAreas();

function parseCount(val) {
    return parseInt(val.replace(/\./g, '').replace(/,/g, '').trim(), 10);
}

// Weight calculator auto-recalc
function editRecalc() {
    const sc   = parseCount(document.getElementById('edit-sample-count').value);
    const sw   = parseFloat(document.getElementById('edit-sample-weight').value);
    const tw   = parseFloat(document.getElementById('edit-total-weight').value);
    const tare = parseFloat(document.getElementById('edit-tare').value) || 0;

    if (!sc || sc <= 0 || !sw || sw <= 0 || !tw || tw <= 0) return;

    const netWeight = tw - tare;
    if (netWeight <= 0) {
        alert('Il peso netto (peso totale − tara) deve essere maggiore di zero.');
        return;
    }

    const qty = (netWeight / sw) * sc;
    document.getElementById('edit-quantity').value = parseFloat(qty.toFixed(4));
}

['edit-sample-count', 'edit-sample-weight', 'edit-total-weight', 'edit-tare'].forEach(id => {
    document.getElementById(id).addEventListener('input', editRecalc);
});
</script>
@endsection
