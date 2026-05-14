@extends('layouts.app')

@section('title', 'Dettaglio Articolo')

@section('extra-styles')
.article-card { border-left: 4px solid #0d6efd; }
.article-card.not-found { border-left-color: #dc3545; }
.lot-warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; }
.weight-calc { background: #f0f9ff; border: 1px solid #0dcaf0; border-radius: 10px; }
.qty-input { font-size: 1.5rem; font-weight: 700; text-align: center; }
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

        {{-- Article info card --}}
        <div class="card border-0 shadow-sm mb-3 article-card {{ $db_source === 'not_found' ? 'not-found' : '' }}">
            <div class="card-header py-2 d-flex align-items-center justify-content-between
                {{ $db_source === 'not_found' ? 'bg-danger text-white' : 'bg-success text-white' }}">
                <span class="fw-bold">
                    @if($db_source === 'not_found')
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Articolo non in DB
                    @else
                        <i class="bi bi-check-circle-fill me-2"></i>Articolo trovato
                        <small class="ms-2 opacity-75">({{ $db_source === 'sqlsrv' ? 'SQL Server' : 'Access' }})</small>
                    @endif
                </span>
                <a href="{{ route('scan') }}" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left"></i> Indietro
                </a>
            </div>
            <div class="card-body p-3">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Codice</th>
                        <td class="fw-bold">{{ $article_code }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Descrizione</th>
                        <td>{{ $description ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">U.M.</th>
                        <td>{{ $um ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Lotto</th>
                        <td>
                            {{ $lot ?: '—' }}
                            @if($lot && !$lot_match && $db_source !== 'not_found')
                                <span class="badge bg-warning text-dark ms-1">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Lotto diverso
                                </span>
                            @elseif($lot && $lot_match)
                                <span class="badge bg-success ms-1">
                                    <i class="bi bi-check-circle-fill"></i> Corrispondente
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Magazzino</th>
                        <td>{{ session('warehouse_name') }} / {{ session('area_name') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($lot && !$lot_match && $db_source !== 'not_found')
            <div class="lot-warning p-3 mb-3">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                <strong>Attenzione:</strong> Il lotto scansionato non corrisponde a quello nel database.
                Verifica che il prodotto sia corretto prima di procedere.
            </div>
        @endif

        {{-- Save form --}}
        <form method="POST" action="{{ route('inventory.save') }}" id="saveForm">
            @csrf
            <input type="hidden" name="article_code" value="{{ $article_code }}">
            <input type="hidden" name="description" value="{{ $description }}">
            <input type="hidden" name="um" value="{{ $um }}">
            <input type="hidden" name="lot" value="{{ $lot }}">
            <input type="hidden" name="db_source" value="{{ $db_source }}">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Weight calculator (if area has it) --}}
                    @if($area->has_weight_calculator)
                        <div class="weight-calc p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 fw-bold text-info">
                                    <i class="bi bi-calculator me-2"></i>Calcolatore Peso
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-info" id="calcToggle"
                                    onclick="toggleCalc()">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                            <div id="calcBody">
                                <div class="row g-2 mb-2">
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Pezzi campione</label>
                                        <input type="number" id="sample_count_calc" name="sample_count"
                                            class="form-control text-center" min="1" placeholder="N."
                                            value="{{ old('sample_count') }}">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Peso campione (kg)</label>
                                        <input type="number" id="sample_weight_calc" name="sample_weight"
                                            class="form-control text-center" step="0.001" min="0.001" placeholder="kg"
                                            value="{{ old('sample_weight') }}">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Peso totale (kg)</label>
                                        <input type="number" id="total_weight_calc" name="total_weight"
                                            class="form-control text-center" step="0.001" min="0" placeholder="kg"
                                            value="{{ old('total_weight') }}">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-info w-100 text-white fw-bold" onclick="calculateQty()">
                                    <i class="bi bi-calculator me-2"></i>Calcola Quantità
                                </button>
                                <small class="text-muted d-block text-center mt-1">
                                    Formula: (Peso totale ÷ Peso campione) × Pezzi campione
                                </small>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="sample_count" value="">
                        <input type="hidden" name="sample_weight" value="">
                        <input type="hidden" name="total_weight" value="">
                    @endif

                    {{-- Quantity --}}
                    <div class="mb-3">
                        <label for="quantity" class="form-label fw-bold fs-5">
                            <i class="bi bi-123 me-1"></i>Quantità *
                            @if($um)
                                <span class="badge bg-secondary ms-1">{{ $um }}</span>
                            @endif
                        </label>
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            class="form-control qty-input @error('quantity') is-invalid @enderror"
                            step="0.0001"
                            min="0"
                            value="{{ old('quantity') }}"
                            placeholder="0"
                            required
                            autofocus
                        >
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Notes --}}
                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">
                            <i class="bi bi-chat-left-text me-1"></i>Note <small class="text-muted">(opzionale)</small>
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            class="form-control"
                            rows="2"
                            placeholder="Note aggiuntive..."
                            maxlength="1000"
                        >{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg fw-bold">
                            <i class="bi bi-floppy-fill me-2"></i>Salva Registrazione
                        </button>
                        <a href="{{ route('scan') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Annulla
                        </a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
@endsection

@section('scripts')
<script>
function calculateQty() {
    const sc = parseFloat(document.getElementById('sample_count_calc')?.value);
    const sw = parseFloat(document.getElementById('sample_weight_calc')?.value);
    const tw = parseFloat(document.getElementById('total_weight_calc')?.value);

    if (!sc || !sw || sw === 0 || isNaN(tw)) {
        alert('Inserisci tutti i valori per il calcolo.');
        return;
    }

    const qty = (tw / sw) * sc;
    document.getElementById('quantity').value = qty.toFixed(4);
    document.getElementById('quantity').focus();
    document.getElementById('quantity').select();
}

function toggleCalc() {
    const body = document.getElementById('calcBody');
    const btn = document.getElementById('calcToggle');
    if (body.style.display === 'none') {
        body.style.display = '';
        btn.innerHTML = '<i class="bi bi-chevron-down"></i>';
    } else {
        body.style.display = 'none';
        btn.innerHTML = '<i class="bi bi-chevron-up"></i>';
    }
}
</script>
@endsection
