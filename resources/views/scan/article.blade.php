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
                        <small class="ms-2 opacity-75">({{ str_starts_with($db_source, 'sqlsrv') ? 'SQL Server' : 'Access' }})</small>
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
                        <th class="text-muted">Scadenza</th>
                        <td>
                            @if($expiry_date)
                                @php $exp = \Carbon\Carbon::parse($expiry_date); @endphp
                                <span class="fw-semibold {{ $exp->isPast() ? 'text-danger' : ($exp->diffInDays() < 30 ? 'text-warning' : 'text-success') }}">
                                    {{ $exp->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-muted small">Non presente su Esolver</span>
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
            <input type="hidden" name="expiry_date" value="{{ $expiry_date }}">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Weight calculator (always available, collapsed by default) --}}
                    <div class="weight-calc p-3 mb-3">
                        <button type="button"
                            class="btn btn-sm btn-outline-info w-100 d-flex align-items-center justify-content-between"
                            id="calcToggle" onclick="toggleCalc()">
                            <span class="fw-bold">
                                <i class="bi bi-calculator me-2"></i>Calcolatore Peso
                            </span>
                            <i class="bi bi-chevron-right" id="calcChevron"></i>
                        </button>
                        <div id="calcBody" style="display:none" class="mt-3">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Pezzi campione</label>
                                    <input type="text" inputmode="numeric" id="sample_count_calc" name="sample_count"
                                        class="form-control text-center" placeholder="N."
                                        value="{{ old('sample_count') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Peso campione (kg)</label>
                                    <input type="number" id="sample_weight_calc" name="sample_weight"
                                        class="form-control text-center" step="0.001" min="0.001" placeholder="kg"
                                        value="{{ old('sample_weight') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Peso totale (kg)</label>
                                    <input type="number" id="total_weight_calc" name="total_weight"
                                        class="form-control text-center" step="0.001" min="0" placeholder="kg"
                                        value="{{ old('total_weight') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">
                                        Tara (kg) <span class="text-muted fw-normal">(opzionale)</span>
                                    </label>
                                    <input type="number" id="tare_calc" name="tare"
                                        class="form-control text-center" step="0.001" min="0" placeholder="0"
                                        value="{{ old('tare', 0) }}">
                                </div>
                            </div>
                            <button type="button" class="btn btn-info w-100 text-white fw-bold" onclick="calculateQty()">
                                <i class="bi bi-calculator me-2"></i>Calcola Quantità
                            </button>
                            <small class="text-muted d-block text-center mt-1">
                                Formula: ((Peso totale − Tara) ÷ Peso campione) × Pezzi campione
                            </small>
                            <div class="alert alert-warning py-2 px-3 mt-2 mb-0 text-start" style="font-size:0.8rem">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Come inserire i numeri:</strong>
                                usa <strong>punto o virgola solo per i decimali</strong> (es. <code>1.5</code> oppure <code>1,5</code> = un chilo e mezzo).
                                Per numeri grandi scrivi tutto attaccato senza separatori (es. <code>10000</code> = diecimila pezzi, non <code>10.000</code>).
                            </div>
                        </div>
                    </div>

                    {{-- UM selezionabile --}}
                    <div class="mb-2">
                        <label class="form-label fw-semibold small mb-1">
                            <i class="bi bi-rulers me-1"></i>Unità di misura
                        </label>
                        <select name="um" id="um-select" class="form-select form-select-sm" style="max-width:200px">
                            @php
                                $currentUm = old('um', $um);
                                $allUms = collect($umList);
                                if ($currentUm && !$allUms->contains($currentUm)) {
                                    $allUms->prepend($currentUm);
                                }
                            @endphp
                            <option value="">— Nessuna UM —</option>
                            @foreach($allUms as $u)
                                <option value="{{ $u }}" {{ $currentUm === $u ? 'selected' : '' }}>{{ $u }}</option>
                            @endforeach
                            <option value="__custom__">+ Altra...</option>
                        </select>
                        <input type="text" id="um-custom" name="_um_custom" class="form-control form-control-sm mt-1 d-none"
                               style="max-width:200px" placeholder="Scrivi unità di misura...">
                    </div>

                    {{-- Quantity --}}
                    <div class="mb-3">
                        <label for="quantity" class="form-label fw-bold fs-5">
                            <i class="bi bi-123 me-1"></i>Quantità *
                        </label>
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            class="form-control qty-input @error('quantity') is-invalid @enderror"
                            step="any"
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
function parseCount(val) {
    return parseInt(val.replace(/\./g, '').replace(/,/g, '').trim(), 10);
}

function calculateQty() {
    const sc   = parseCount(document.getElementById('sample_count_calc').value);
    const sw   = parseFloat(document.getElementById('sample_weight_calc').value);
    const tw   = parseFloat(document.getElementById('total_weight_calc').value);
    const tare = parseFloat(document.getElementById('tare_calc').value) || 0;

    if (!sc || sc <= 0 || !sw || sw <= 0 || isNaN(tw) || tw <= 0) {
        alert('Inserisci pezzi campione, peso campione e peso totale per il calcolo.');
        return;
    }

    const netWeight = tw - tare;
    if (netWeight <= 0) {
        alert('Il peso netto (peso totale − tara) deve essere maggiore di zero.');
        return;
    }

    const qty = (netWeight / sw) * sc;
    const display = Number.isInteger(qty) ? qty.toString() : parseFloat(qty.toFixed(4)).toString();
    document.getElementById('quantity').value = display;
    document.getElementById('quantity').focus();
    document.getElementById('quantity').select();
}

function toggleCalc() {
    const body    = document.getElementById('calcBody');
    const chevron = document.getElementById('calcChevron');
    const open    = body.style.display === 'none';
    body.style.display    = open ? '' : 'none';
    chevron.className     = open ? 'bi bi-chevron-down' : 'bi bi-chevron-right';
}

// UM select: mostra campo libero su "Altra..."
const umSelect = document.getElementById('um-select');
const umCustom = document.getElementById('um-custom');

umSelect.addEventListener('change', function () {
    if (this.value === '__custom__') {
        umCustom.classList.remove('d-none');
        umCustom.required = true;
        umCustom.focus();
    } else {
        umCustom.classList.add('d-none');
        umCustom.required = false;
        umCustom.value = '';
    }
});

// Prima del submit: se "Altra..." è selezionato, sposta il valore nel select
document.querySelector('form').addEventListener('submit', function () {
    if (umSelect.value === '__custom__' && umCustom.value.trim()) {
        const opt = document.createElement('option');
        opt.value = umCustom.value.trim();
        opt.selected = true;
        umSelect.appendChild(opt);
        umCustom.name = '';
    }
});
</script>
@endsection
