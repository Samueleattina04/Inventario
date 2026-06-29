@extends('layouts.admin')

@section('title', 'Rettifica')
@section('breadcrumb')
    <li class="breadcrumb-item active">Rettifica</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-warning"></i>Rettifica Registrazione</h4>
</div>

{{-- Search by ID --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.rettifica.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small">ID Registrazione</label>
                <input type="number" name="id" class="form-control" placeholder="Inserisci ID registrazione..."
                       value="{{ request('id') }}" min="1" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Cerca</button>
            </div>
        </form>
    </div>
</div>

@if($notFound)
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Nessuna registrazione trovata con ID <strong>#{{ request('id') }}</strong>.
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show">
        <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($record)
{{-- Record info --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-secondary text-white">
        <i class="bi bi-info-circle me-2"></i>Registrazione #{{ $record->id }}
        @if($record->rettified)
            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-pencil-square me-1"></i>Già rettificata</span>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">Operatore</div>
                <div class="fw-semibold">{{ $record->user?->name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Data/Ora</div>
                <div class="fw-semibold">{{ $record->created_at->format('d/m/Y H:i') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Magazzino</div>
                <div class="fw-semibold">{{ $record->warehouse?->name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Area</div>
                <div class="fw-semibold">{{ $record->area?->name ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Edit form --}}
<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold bg-warning text-dark">
        <i class="bi bi-pencil me-2"></i>Modifica dati
    </div>
    <div class="card-body">
        <form id="rettificaForm" method="POST" action="{{ route('admin.rettifica.update', $record) }}" novalidate>
            @csrf @method('PUT')

            <div class="row g-3">
                {{-- Article code with live search --}}
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Codice Articolo <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="text" id="articleInput" class="form-control @error('article_code') is-invalid @enderror"
                               placeholder="Digita per cercare o inserisci manualmente..."
                               value="{{ old('article_code', $record->article_code) }}"
                               autocomplete="off">
                        <input type="hidden" name="article_code" id="articleCode" value="{{ old('article_code', $record->article_code) }}">
                        <input type="hidden" name="description" id="articleDescription" value="{{ old('description', $record->description) }}">
                        <input type="hidden" name="um" id="articleUmHidden" value="{{ old('um', $record->um) }}">
                        <div id="articleDropdown" class="dropdown-menu w-100" style="display:none; max-height:250px; overflow-y:auto; position:absolute; z-index:1055;"></div>
                        @error('article_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="text-muted small mt-1" id="articleDescPreview">
                        @if($record->description) {{ $record->description }} @endif
                    </div>
                </div>

                {{-- UM --}}
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold">U.M.</label>
                    <input type="text" id="articleUmField" class="form-control @error('um') is-invalid @enderror"
                           name="um_display" value="{{ old('um', $record->um) }}"
                           placeholder="es. KG">
                    @error('um')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Lot --}}
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold">Lotto</label>
                    <input type="text" name="lot" class="form-control @error('lot') is-invalid @enderror"
                           id="lotField" value="{{ old('lot', $record->lot) }}" placeholder="es. 01042025">
                    @error('lot')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Quantity --}}
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold">Quantità <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror"
                           id="quantityField" value="{{ old('quantity', $record->quantity) }}"
                           step="0.0001" min="0" required>
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="button" class="btn btn-warning" id="btnShowConfirm">
                    <i class="bi bi-save me-1"></i>Salva rettifica
                </button>
                <a href="{{ route('admin.inventory.show', $record) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x me-1"></i>Annulla
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Confirmation Modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Conferma Rettifica #{{ $record->id }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Verifica le modifiche prima di salvare:</p>
                <div id="noChangesMsg" class="alert alert-info py-2" style="display:none">
                    <i class="bi bi-info-circle me-1"></i>Nessuna modifica rilevata.
                </div>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Campo</th>
                            <th class="text-danger">Valore precedente</th>
                            <th class="text-success">Nuovo valore</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="rowArticleCode">
                            <td class="fw-semibold">Codice articolo</td>
                            <td class="text-danger" id="oldArticleCode">{{ $record->article_code }}</td>
                            <td class="text-success fw-bold" id="newArticleCode"></td>
                        </tr>
                        <tr id="rowLot">
                            <td class="fw-semibold">Lotto</td>
                            <td class="text-danger" id="oldLot">{{ $record->lot ?: '—' }}</td>
                            <td class="text-success fw-bold" id="newLot"></td>
                        </tr>
                        <tr id="rowQuantity">
                            <td class="fw-semibold">Quantità</td>
                            <td class="text-danger" id="oldQuantity">{{ number_format($record->quantity, 2, ',', '.') }}</td>
                            <td class="text-success fw-bold" id="newQuantity"></td>
                        </tr>
                        <tr id="rowUm">
                            <td class="fw-semibold">U.M.</td>
                            <td class="text-danger" id="oldUm">{{ $record->um ?: '—' }}</td>
                            <td class="text-success fw-bold" id="newUm"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Annulla
                </button>
                <button type="button" class="btn btn-warning" id="btnConfirmSave">
                    <i class="bi bi-check-lg me-1"></i>Salva
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
@if($record)
// ---- Article live search ----
const articleInput       = document.getElementById('articleInput');
const articleCodeHidden  = document.getElementById('articleCode');
const articleDescHidden  = document.getElementById('articleDescription');
const articleUmHidden    = document.getElementById('articleUmHidden');
const articleUmField     = document.getElementById('articleUmField');
const articleDropdown    = document.getElementById('articleDropdown');
const articleDescPreview = document.getElementById('articleDescPreview');

let searchTimer = null;

articleInput.addEventListener('input', function () {
    const q = this.value.trim();
    articleCodeHidden.value = q;
    clearTimeout(searchTimer);
    if (q.length < 2) { articleDropdown.style.display = 'none'; return; }
    searchTimer = setTimeout(() => fetchArticles(q), 300);
});

articleInput.addEventListener('blur', function () {
    setTimeout(() => { articleDropdown.style.display = 'none'; }, 200);
});

function fetchArticles(q) {
    fetch(`/api/articles/search?q=${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        articleDropdown.innerHTML = '';
        if (!data.length) { articleDropdown.style.display = 'none'; return; }
        data.forEach(item => {
            const el = document.createElement('a');
            el.href = '#';
            el.className = 'dropdown-item py-2';
            el.innerHTML = `<code class="me-2">${item.code}</code><span class="text-muted small">${item.description || ''}</span>`;
            el.addEventListener('mousedown', function (e) {
                e.preventDefault();
                articleInput.value       = item.code;
                articleCodeHidden.value  = item.code;
                articleDescHidden.value  = item.description || '';
                articleUmHidden.value    = item.um || '';
                articleUmField.value     = item.um || articleUmField.value;
                articleDescPreview.textContent = item.description || '';
                articleDropdown.style.display = 'none';
            });
            articleDropdown.appendChild(el);
        });
        articleDropdown.style.display = 'block';
    })
    .catch(() => { articleDropdown.style.display = 'none'; });
}

// Sync UM display field to hidden
articleUmField.addEventListener('input', function () {
    articleUmHidden.value = this.value;
});

// ---- Confirmation modal ----
const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));

document.getElementById('btnShowConfirm').addEventListener('click', function () {
    const newCode = articleCodeHidden.value.trim();
    const newLot  = document.getElementById('lotField').value.trim();
    const newQty  = parseFloat(document.getElementById('quantityField').value);
    const newUm   = articleUmField.value.trim();

    if (!newCode) {
        articleInput.classList.add('is-invalid');
        return;
    }
    if (isNaN(newQty) || newQty < 0) {
        document.getElementById('quantityField').classList.add('is-invalid');
        return;
    }

    const oldCode = document.getElementById('oldArticleCode').textContent.trim();
    const oldLot  = document.getElementById('oldLot').textContent.trim();
    const oldQty  = document.getElementById('oldQuantity').textContent.trim();
    const oldUm   = document.getElementById('oldUm').textContent.trim();

    const newQtyFmt = newQty.toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:4});

    const rows = [
        { rowId: 'rowArticleCode', oldVal: oldCode,          newVal: newCode,    newElem: 'newArticleCode' },
        { rowId: 'rowLot',         oldVal: oldLot,           newVal: newLot || '—', newElem: 'newLot' },
        { rowId: 'rowQuantity',    oldVal: oldQty,           newVal: newQtyFmt,  newElem: 'newQuantity' },
        { rowId: 'rowUm',          oldVal: oldUm,            newVal: newUm || '—', newElem: 'newUm' },
    ];

    let hasChanges = false;
    rows.forEach(r => {
        document.getElementById(r.newElem).textContent = r.newVal;
        const changed = r.oldVal !== r.newVal;
        document.getElementById(r.rowId).style.display = changed ? '' : 'none';
        if (changed) hasChanges = true;
    });

    document.getElementById('noChangesMsg').style.display = hasChanges ? 'none' : '';
    document.getElementById('btnConfirmSave').disabled = !hasChanges;

    confirmModal.show();
});

document.getElementById('btnConfirmSave').addEventListener('click', function () {
    // Sync hidden um field from display field before submit
    articleUmHidden.value = articleUmField.value;
    document.getElementById('rettificaForm').submit();
});
@endif
</script>
@endsection
