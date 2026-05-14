@extends('layouts.app')

@section('title', 'Scanner')

@section('extra-styles')
#qr-reader, #barcode-reader {
    width: 100% !important;
    border-radius: 12px;
    overflow: hidden;
}
#qr-reader video, #barcode-reader video {
    border-radius: 12px;
}
.scanner-box {
    background: #000;
    border-radius: 12px;
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.scanner-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 180px;
    height: 180px;
    border: 3px solid #fff;
    border-radius: 12px;
    pointer-events: none;
    z-index: 10;
}
.scanner-overlay::before,
.scanner-overlay::after {
    content: '';
    position: absolute;
    width: 30px;
    height: 30px;
    border-color: #0d6efd;
    border-style: solid;
}
.scanner-overlay::before {
    top: -3px; left: -3px;
    border-width: 4px 0 0 4px;
    border-radius: 6px 0 0 0;
}
.scanner-overlay::after {
    bottom: -3px; right: -3px;
    border-width: 0 4px 4px 0;
    border-radius: 0 0 6px 0;
}
.toast-container { z-index: 9999; }
.nav-tabs .nav-link { min-height: 44px; font-size: 1rem; font-weight: 500; }
.manual-input-group input { min-height: 48px; font-size: 1rem; }
.btn-scan-action { min-height: 52px; font-size: 1.05rem; }
#startQrBtn, #stopQrBtn, #startBarcodeBtn, #stopBarcodeBtn {
    min-height: 48px;
}
@endsection

@section('content')

{{-- Toast container --}}
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="scanToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

{{-- Loading overlay --}}
<div id="loadingOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="text-center text-white">
        <div class="spinner-border mb-2" role="status"></div>
        <div>Ricerca articolo...</div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

        {{-- Location bar --}}
        <div class="alert alert-primary py-2 mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill fs-5"></i>
            <div>
                <strong>{{ $warehouseName }}</strong> &rarr; <strong>{{ $areaName }}</strong>
            </div>
            <a href="{{ route('location') }}" class="btn btn-sm btn-outline-primary ms-auto">
                <i class="bi bi-pencil-square"></i> Cambia
            </a>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs nav-fill mb-3" id="scannerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="qr-tab" data-bs-toggle="tab" data-bs-target="#qr-panel"
                    type="button" role="tab" aria-controls="qr-panel" aria-selected="true">
                    <i class="bi bi-qr-code me-1"></i>QR Code
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="barcode-tab" data-bs-toggle="tab" data-bs-target="#barcode-panel"
                    type="button" role="tab" aria-controls="barcode-panel" aria-selected="false">
                    <i class="bi bi-upc-scan me-1"></i>Codice a Barre
                </button>
            </li>
        </ul>

        <div class="tab-content">

            {{-- ====== QR TAB ====== --}}
            <div class="tab-pane fade show active" id="qr-panel" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div id="qr-reader" class="mb-3"></div>
                        <div class="d-flex gap-2 mb-3">
                            <button id="startQrBtn" class="btn btn-primary flex-fill" onclick="startScanner('qr')">
                                <i class="bi bi-camera-fill me-1"></i>Avvia Camera
                            </button>
                            <button id="stopQrBtn" class="btn btn-secondary flex-fill d-none" onclick="stopScanner('qr')">
                                <i class="bi bi-camera-video-off me-1"></i>Ferma
                            </button>
                        </div>

                        <hr class="my-2">
                        <p class="text-muted small text-center mb-2">Oppure inserisci manualmente:</p>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Codice Articolo</label>
                            <input type="text" id="qr-article-code" class="form-control"
                                placeholder="Codice articolo..." autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Lotto</label>
                            <input type="text" id="qr-lot" class="form-control"
                                placeholder="Numero lotto..." autocomplete="off">
                        </div>
                        <button class="btn btn-success btn-scan-action w-100" onclick="manualLookup('qr')">
                            <i class="bi bi-search me-2"></i>Cerca Articolo
                        </button>
                    </div>
                </div>
            </div>

            {{-- ====== BARCODE TAB ====== --}}
            <div class="tab-pane fade" id="barcode-panel" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div id="barcode-reader" class="mb-3"></div>
                        <div class="d-flex gap-2 mb-3">
                            <button id="startBarcodeBtn" class="btn btn-primary flex-fill" onclick="startScanner('barcode')">
                                <i class="bi bi-camera-fill me-1"></i>Avvia Camera
                            </button>
                            <button id="stopBarcodeBtn" class="btn btn-secondary flex-fill d-none" onclick="stopScanner('barcode')">
                                <i class="bi bi-camera-video-off me-1"></i>Ferma
                            </button>
                        </div>

                        <hr class="my-2">
                        <p class="text-muted small text-center mb-2">Oppure inserisci manualmente:</p>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Codice Articolo</label>
                            <input type="text" id="barcode-article-code" class="form-control"
                                placeholder="Codice articolo..." autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Lotto</label>
                            <input type="text" id="barcode-lot" class="form-control"
                                placeholder="Numero lotto..." autocomplete="off">
                        </div>
                        <button class="btn btn-success btn-scan-action w-100" onclick="manualLookup('barcode')">
                            <i class="bi bi-search me-2"></i>Cerca Articolo
                        </button>
                    </div>
                </div>
            </div>

        </div>{{-- /tab-content --}}

        {{-- Not-found override panel (hidden by default) --}}
        <div id="notFoundPanel" class="card border-warning border-2 shadow-sm mt-3 d-none">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Articolo non trovato in nessun database
            </div>
            <div class="card-body p-3">
                <p class="text-muted small">Puoi inserire manualmente le informazioni mancanti:</p>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Codice Articolo</label>
                    <input type="text" id="override-code" class="form-control" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Descrizione *</label>
                    <input type="text" id="override-desc" class="form-control" placeholder="Inserisci descrizione...">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Unità di Misura</label>
                    <input type="text" id="override-um" class="form-control" placeholder="es. PZ, KG, MT...">
                </div>
                <button class="btn btn-warning w-100 fw-bold" onclick="proceedWithOverride()">
                    <i class="bi bi-arrow-right-circle me-2"></i>Continua con questi dati
                </button>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let qrScanner = null;
let barcodeScanner = null;
let activeTab = 'qr';
let lastScanData = {};

const BARCODE_FORMATS = [
    Html5QrcodeSupportedFormats.EAN_13,
    Html5QrcodeSupportedFormats.EAN_8,
    Html5QrcodeSupportedFormats.CODE_128,
    Html5QrcodeSupportedFormats.CODE_39,
    Html5QrcodeSupportedFormats.UPC_A,
    Html5QrcodeSupportedFormats.UPC_E,
    Html5QrcodeSupportedFormats.ITF,
    Html5QrcodeSupportedFormats.CODABAR,
];

const QR_FORMATS = [Html5QrcodeSupportedFormats.QR_CODE];

function showToast(message, type = 'danger') {
    const toast = document.getElementById('scanToast');
    const msg = document.getElementById('toastMessage');
    toast.className = `toast align-items-center text-white border-0 bg-${type}`;
    msg.textContent = message;
    const bsToast = bootstrap.Toast.getOrCreateInstance(toast, { delay: 4000 });
    bsToast.show();
}

function setLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.toggle('d-none', !show);
    overlay.classList.toggle('d-flex', show);
}

async function startScanner(type) {
    const readerId = type === 'qr' ? 'qr-reader' : 'barcode-reader';
    const formats = type === 'qr' ? QR_FORMATS : BARCODE_FORMATS;

    const startBtn = document.getElementById(type === 'qr' ? 'startQrBtn' : 'startBarcodeBtn');
    const stopBtn  = document.getElementById(type === 'qr' ? 'stopQrBtn' : 'stopBarcodeBtn');

    const scanner = new Html5Qrcode(readerId);
    if (type === 'qr') qrScanner = scanner;
    else barcodeScanner = scanner;

    try {
        await scanner.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                formatsToSupport: formats,
            },
            (decodedText, decodedResult) => onScanSuccess(decodedText, decodedResult, type),
            () => {}
        );
        startBtn.classList.add('d-none');
        stopBtn.classList.remove('d-none');
    } catch (err) {
        showToast('Impossibile avviare la camera: ' + err, 'danger');
    }
}

async function stopScanner(type) {
    const scanner = type === 'qr' ? qrScanner : barcodeScanner;
    if (!scanner) return;

    try {
        await scanner.stop();
        scanner.clear();
    } catch (e) {}

    if (type === 'qr') qrScanner = null;
    else barcodeScanner = null;

    const startBtn = document.getElementById(type === 'qr' ? 'startQrBtn' : 'startBarcodeBtn');
    const stopBtn  = document.getElementById(type === 'qr' ? 'stopQrBtn' : 'stopBarcodeBtn');
    startBtn.classList.remove('d-none');
    stopBtn.classList.add('d-none');
}

function onScanSuccess(decodedText, decodedResult, expectedType) {
    const format = decodedResult.result.format.formatName;
    const isQr = format === 'QR_CODE';

    if (expectedType === 'qr' && !isQr) {
        showToast("Errore: hai scansionato un codice a barre. Usa la scheda 'Codice a Barre'.", 'danger');
        return;
    }
    if (expectedType === 'barcode' && isQr) {
        showToast("Errore: hai scansionato un QR Code. Usa la scheda 'QR Code'.", 'danger');
        return;
    }

    // Stop scanner after successful read
    stopScanner(expectedType);

    // Use scanned text as lot; no separate article code from scan alone
    // For QR: treat the raw string as the combined value
    // For simplicity, treat entire scanned text as lot; article code stays from manual field
    const articleCodeField = document.getElementById(expectedType === 'qr' ? 'qr-article-code' : 'barcode-article-code');
    const lotField = document.getElementById(expectedType === 'qr' ? 'qr-lot' : 'barcode-lot');

    // If article code not filled, put scanned text there; otherwise use as lot
    if (!articleCodeField.value.trim()) {
        articleCodeField.value = decodedText;
    } else {
        lotField.value = decodedText;
    }

    // Auto-lookup if we have article code
    if (articleCodeField.value.trim()) {
        doLookup(articleCodeField.value.trim(), lotField.value.trim(), expectedType);
    } else {
        showToast('Codice scansionato. Inserisci il codice articolo se necessario.', 'info');
    }
}

function manualLookup(type) {
    const articleCode = document.getElementById(type === 'qr' ? 'qr-article-code' : 'barcode-article-code').value.trim();
    const lot = document.getElementById(type === 'qr' ? 'qr-lot' : 'barcode-lot').value.trim();

    if (!articleCode) {
        showToast('Inserisci il codice articolo.', 'warning');
        return;
    }

    doLookup(articleCode, lot, type);
}

async function doLookup(articleCode, lot, scanType) {
    setLoading(true);
    document.getElementById('notFoundPanel').classList.add('d-none');

    try {
        const resp = await fetch('{{ route('api.article-lookup') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ article_code: articleCode, lot: lot, scan_type: scanType }),
        });

        const data = await resp.json();
        setLoading(false);

        if (data.found) {
            // Redirect to article page
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
            // Show override panel
            document.getElementById('override-code').value = articleCode;
            document.getElementById('override-desc').value = '';
            document.getElementById('override-um').value = '';
            lastScanData = { article_code: articleCode, lot: lot, db_source: 'not_found' };
            document.getElementById('notFoundPanel').classList.remove('d-none');
            document.getElementById('notFoundPanel').scrollIntoView({ behavior: 'smooth' });
        }
    } catch (err) {
        setLoading(false);
        showToast('Errore di rete: ' + err.message, 'danger');
    }
}

function proceedWithOverride() {
    const desc = document.getElementById('override-desc').value.trim();
    if (!desc) {
        showToast('Inserisci una descrizione.', 'warning');
        return;
    }
    const params = new URLSearchParams({
        article_code: lastScanData.article_code,
        description:  desc,
        um:           document.getElementById('override-um').value.trim(),
        lot:          lastScanData.lot,
        db_source:    'not_found',
        lot_match:    '0',
    });
    window.location.href = '{{ route('article') }}?' + params.toString();
}

// Stop active scanner when switching tabs
document.querySelectorAll('#scannerTabs [data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('hide.bs.tab', function(e) {
        const targetId = e.target.id;
        if (targetId === 'qr-tab' && qrScanner) stopScanner('qr');
        if (targetId === 'barcode-tab' && barcodeScanner) stopScanner('barcode');
    });
});
</script>
@endsection
