@extends('layouts.app')

@section('title', 'Scanner')

@section('extra-styles')
#qr-reader { width: 100% !important; border-radius: 12px; overflow: hidden; }
#qr-reader video { border-radius: 12px; }
.toast-container { z-index: 9999; }
.btn-scan-action { min-height: 52px; font-size: 1.05rem; }
#startQrBtn, #stopQrBtn { min-height: 48px; }
.article-result-item { cursor: pointer; border: 2px solid transparent; transition: .15s; }
.article-result-item:hover, .article-result-item.selected { border-color: #0d6efd; background: #f0f5ff; }
.lot-badge { font-size: 1rem; font-weight: 600; letter-spacing: .03em; }
@endsection

@section('content')

{{-- Toast --}}
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="scanToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

{{-- Loading --}}
<div id="loadingOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="text-center text-white">
        <div class="spinner-border mb-2" role="status"></div>
        <div>Ricerca in corso...</div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

        {{-- Location bar --}}
        <div class="alert alert-primary py-2 mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill fs-5"></i>
            <div><strong>{{ $warehouseName }}</strong> &rarr; <strong>{{ $areaName }}</strong></div>
            <a href="{{ route('location') }}" class="btn btn-sm btn-outline-primary ms-auto">
                <i class="bi bi-pencil-square"></i> Cambia
            </a>
        </div>

        {{-- Scanner card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">

                {{-- Camera QR --}}
                <div id="qr-reader" class="mb-3" style="display:none;"></div>
                <div class="d-flex gap-2 mb-3">
                    <button id="startQrBtn" class="btn btn-outline-primary flex-fill" onclick="startScanner()">
                        <i class="bi bi-qr-code-scan me-1"></i>Scansiona QR con Camera
                    </button>
                    <button id="stopQrBtn" class="btn btn-outline-secondary flex-fill d-none" onclick="stopScanner()">
                        <i class="bi bi-camera-video-off me-1"></i>Ferma Camera
                    </button>
                </div>

                <hr class="my-2">

                {{-- Lot / article code input (also receives barcode scanner input) --}}
                <label class="form-label fw-semibold">Lotto, QR o Codice Articolo</label>
                <div class="input-group mb-2">
                    <input type="text" id="lotInput" class="form-control form-control-lg"
                        placeholder="Scansiona o inserisci lotto / codice articolo"
                        autocomplete="off" autocorrect="off" spellcheck="false">
                    <button class="btn btn-primary px-3" onclick="manualLookup()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <small class="text-muted">Con palmare: la ricerca parte in automatico dopo la scansione</small>

            </div>
        </div>

        {{-- Not-found panel --}}
        <div id="notFoundPanel" class="card border-warning border-2 shadow-sm d-none">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Lotto non trovato nel database
            </div>
            <div class="card-body p-3">

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Lotto inserito</label>
                    <div class="lot-badge text-primary" id="notFoundLot"></div>
                </div>

                <p class="text-muted small mb-2">Cerca il codice articolo manualmente:</p>

                {{-- Article search --}}
                <div class="input-group mb-2">
                    <input type="text" id="articleSearchInput" class="form-control"
                        placeholder="Cerca per codice o descrizione..." autocomplete="off">
                    <button class="btn btn-outline-secondary" onclick="searchArticles()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div id="articleSearchResults" class="mb-3" style="max-height:220px; overflow-y:auto;"></div>

                {{-- Selected article + quantity --}}
                <div id="selectedArticlePanel" class="d-none">
                    <div class="alert alert-success py-2 mb-3">
                        <strong id="selectedCode"></strong>
                        <div class="small text-muted" id="selectedDesc"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" id="quantityLabel">Quantità</label>
                        <input type="number" id="overrideQty" class="form-control form-control-lg text-center"
                            step="0.001" min="0" placeholder="0">
                    </div>

                    <button class="btn btn-warning w-100 fw-bold btn-scan-action" onclick="proceedWithOverride()">
                        <i class="bi bi-arrow-right-circle me-2"></i>Continua
                    </button>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let qrScanner = null;
let lastLot = '';
let selectedArticle = null;
let searchTimeout = null;

// ── Toast ──────────────────────────────────────────────────────────────────
function showToast(message, type = 'danger') {
    const toast = document.getElementById('scanToast');
    toast.className = `toast align-items-center text-white border-0 bg-${type}`;
    document.getElementById('toastMessage').textContent = message;
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 4000 }).show();
}

function setLoading(show) {
    const el = document.getElementById('loadingOverlay');
    el.classList.toggle('d-none', !show);
    el.classList.toggle('d-flex', show);
}

// ── Camera QR ──────────────────────────────────────────────────────────────
async function startScanner() {
    const reader = document.getElementById('qr-reader');
    reader.style.display = '';
    qrScanner = new Html5Qrcode('qr-reader');
    try {
        await qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 }, formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] },
            (decodedText) => { if (!qrScanner) return; stopScanner(); doLookup(decodedText); },
            () => {}
        );
        document.getElementById('startQrBtn').classList.add('d-none');
        document.getElementById('stopQrBtn').classList.remove('d-none');
    } catch (err) {
        reader.style.display = 'none';
        showToast('Impossibile avviare la camera: ' + err, 'danger');
    }
}

async function stopScanner() {
    if (!qrScanner) return;
    try { await qrScanner.stop(); qrScanner.clear(); } catch (e) {}
    qrScanner = null;
    document.getElementById('qr-reader').style.display = 'none';
    document.getElementById('startQrBtn').classList.remove('d-none');
    document.getElementById('stopQrBtn').classList.add('d-none');
}

// ── Lookup ─────────────────────────────────────────────────────────────────
function manualLookup() {
    const lot = document.getElementById('lotInput').value.trim();
    if (!lot) { showToast('Inserisci il valore del lotto.', 'warning'); return; }
    doLookup(lot);
}

async function doLookup(lotValue) {
    setLoading(true);
    hideNotFound();

    try {
        const resp = await fetch('{{ route('api.article-lookup') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ lot: lotValue, scan_type: 'unified' }),
        });

        const data = await resp.json();
        setLoading(false);

        if (data.found) {
            const params = new URLSearchParams({
                article_code: data.article_code,
                description:  data.description,
                um:           data.um,
                lot:          data.lot,
                db_source:    data.source,
                lot_match:    data.lot_match ? '1' : '0',
            });
            window.location.href = '{{ route('article') }}?' + params.toString();
        } else {
            showNotFound(lotValue);
        }
    } catch (err) {
        setLoading(false);
        showToast('Errore di rete: ' + err.message, 'danger');
    }
}

// ── Not-found panel ────────────────────────────────────────────────────────
function showNotFound(lot) {
    lastLot = lot;
    document.getElementById('notFoundLot').textContent = lot;
    document.getElementById('notFoundPanel').classList.remove('d-none');
    document.getElementById('notFoundPanel').scrollIntoView({ behavior: 'smooth' });
    document.getElementById('articleSearchInput').value = '';
    document.getElementById('articleSearchResults').innerHTML = '';
    document.getElementById('selectedArticlePanel').classList.add('d-none');
    selectedArticle = null;
}

function hideNotFound() {
    document.getElementById('notFoundPanel').classList.add('d-none');
}

// ── Article search ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('articleSearchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('articleSearchResults').innerHTML = '';
            return;
        }
        searchTimeout = setTimeout(() => searchArticles(), 400);
    });

    document.getElementById('articleSearchInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); searchArticles(); }
    });

    // ── Palmare / barcode scanner auto-submit ──────────────────────────────
    // Solo scanner Zebra: tutti i char in ~20-30ms → scatta automaticamente.
    // Digitazione manuale: nessun auto-submit, si usa il bottone di ricerca.
    let scanStartTime = null;
    let scanTimer = null;
    let lookupPending = false;

    const lotInput = document.getElementById('lotInput');

    function fireLookup() {
        if (lookupPending) return;
        const val = lotInput.value.replace(/[\r\n]/g, '').trim();
        if (!val) return;
        lookupPending = true;
        lotInput.value = val;
        clearTimeout(scanTimer);
        scanStartTime = null;
        manualLookup();
        setTimeout(() => { lookupPending = false; }, 1500);
    }

    // Enter/CR (se Zebra configurato con suffisso CR)
    lotInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); fireLookup(); }
    });

    lotInput.addEventListener('input', function() {
        // CR/LF iniettato nel valore (alcuni Zebra)
        if (/[\r\n]/.test(this.value)) { fireLookup(); return; }

        clearTimeout(scanTimer);
        const val = this.value.trim();
        if (!val) { scanStartTime = null; return; }

        if (!scanStartTime) scanStartTime = Date.now();
        const elapsed = Date.now() - scanStartTime;

        // Scanner: >= 6 chars arrivati in < 60ms → impossibile per digitazione umana
        scanTimer = setTimeout(() => {
            if (lotInput.value.trim().length >= 6 && elapsed < 60) fireLookup();
            else scanStartTime = null;
        }, 60);
    });

    lotInput.focus();
});

async function searchArticles() {
    const q = document.getElementById('articleSearchInput').value.trim();
    if (q.length < 2) return;

    const container = document.getElementById('articleSearchResults');
    container.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div></div>';

    try {
        const resp = await fetch('{{ route('api.articles.search') }}?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' },
        });
        const data = await resp.json();

        if (!data.length) {
            container.innerHTML = '<p class="text-muted small text-center py-2">Nessun risultato.</p>';
            return;
        }

        container.innerHTML = data.map(a => `
            <div class="article-result-item p-2 rounded mb-1 border"
                 onclick="selectArticle(${JSON.stringify(a).replace(/"/g, '&quot;')})">
                <div class="fw-semibold small">${a.code}</div>
                <div class="text-muted" style="font-size:.8rem;">${a.description}</div>
                <span class="badge bg-secondary">${a.um_label}</span>
            </div>
        `).join('');
    } catch (err) {
        container.innerHTML = '<p class="text-danger small text-center py-2">Errore nella ricerca.</p>';
    }
}

function selectArticle(article) {
    selectedArticle = article;

    document.querySelectorAll('.article-result-item').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');

    document.getElementById('selectedCode').textContent = article.code;
    document.getElementById('selectedDesc').textContent = article.description;
    document.getElementById('quantityLabel').textContent = `Quantità (${article.um_label})`;
    document.getElementById('overrideQty').value = '';
    document.getElementById('selectedArticlePanel').classList.remove('d-none');
    document.getElementById('overrideQty').focus();
}

function proceedWithOverride() {
    if (!selectedArticle) { showToast('Seleziona un articolo.', 'warning'); return; }
    const qty = parseFloat(document.getElementById('overrideQty').value);
    if (!qty || qty <= 0) { showToast('Inserisci una quantità valida.', 'warning'); return; }

    const params = new URLSearchParams({
        article_code: selectedArticle.code,
        description:  selectedArticle.description,
        um:           selectedArticle.um_label,
        lot:          lastLot,
        db_source:    'not_found',
        lot_match:    '0',
    });
    window.location.href = '{{ route('article') }}?' + params.toString();
}
</script>
@endsection
