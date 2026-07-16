<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario — Consultazione</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f4f8; }
        .topbar { background:#1a2e4a; color:#fff; padding:0.75rem 1.25rem; }
        .summary-row { cursor: pointer; }
        .summary-row:hover { background-color: #f8f9fa; }
        .detail-table thead { background-color: #e9ecef; }
    </style>
</head>
<body>

<div class="topbar d-flex align-items-center justify-content-between">
    <span class="fw-bold fs-5"><i class="bi bi-box-seam me-2"></i>Inventario — Consultazione</span>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-light">
            <i class="bi bi-box-arrow-right me-1"></i>Esci
        </button>
    </form>
</div>

<div class="container-fluid p-3 p-md-4">

    {{-- Search --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('viewer.index') }}" class="d-flex gap-2 align-items-center flex-wrap">
                <div class="flex-grow-1">
                    <input type="text" name="search" class="form-control form-control-lg"
                           placeholder="Inserisci il codice articolo esatto..."
                           value="{{ $search }}" autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-search me-1"></i>Cerca</button>
                @if($search)
                    <a href="{{ route('viewer.index') }}" class="btn btn-outline-secondary btn-lg"><i class="bi bi-x"></i></a>
                @endif
            </form>
        </div>
    </div>

    @if($search && !$summary)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>Nessuna registrazione trovata per il codice <strong>{{ $search }}</strong>.
        </div>
    @endif

    @if($summary)
    {{-- Summary row --}}
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body summary-row d-flex align-items-center gap-3 py-3"
             data-bs-toggle="collapse" data-bs-target="#detailsCollapse" aria-expanded="false">
            <i class="bi bi-chevron-right text-muted toggle-icon" style="transition:.2s;"></i>
            <div class="flex-grow-1">
                <div class="fw-bold fs-5"><code>{{ $summary->article_code }}</code></div>
                <div class="text-muted small">{{ $summary->description }}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-4 text-primary">{{ number_format($summary->total_qty, 2, ',', '.') }}
                    @if($summary->um) <small class="text-muted fs-6">{{ $summary->um }}</small> @endif
                </div>
                <div class="text-muted small">{{ $summary->record_count }} {{ $summary->record_count == 1 ? 'registrazione' : 'registrazioni' }}</div>
            </div>
        </div>
    </div>

    {{-- Detail rows (collapsed by default) --}}
    <div class="collapse" id="detailsCollapse">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-sm detail-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data/Ora</th>
                            <th>Operatore</th>
                            <th>Magazzino / Area</th>
                            <th>Lotto</th>
                            <th>Scadenza</th>
                            <th class="text-end">Quantità</th>
                            <th>DB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($details as $record)
                        <tr>
                            <td class="text-muted small">#{{ $record->id }}</td>
                            <td class="small text-nowrap">{{ $record->created_at->format('d/m/Y H:i') }}</td>
                            <td class="small">{{ $record->user?->name }}</td>
                            <td class="small">
                                <div class="fw-semibold">{{ $record->warehouse?->name }}</div>
                                <div class="text-muted">{{ $record->area?->name }}</div>
                            </td>
                            <td class="small">{{ $record->lot ?: '—' }}</td>
                            <td class="small text-nowrap">
                                @if($record->expiry_date)
                                    @php $exp = $record->expiry_date; @endphp
                                    <span class="{{ $exp->isPast() ? 'text-danger fw-bold' : ($exp->diffInDays() < 30 ? 'text-warning fw-semibold' : 'text-success') }}">
                                        {{ $exp->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold">{{ number_format($record->quantity, 2, ',', '.') }}</td>
                            <td>
                                @if(str_starts_with($record->db_source ?? '', 'sqlsrv'))
                                    <span class="badge bg-primary" title="SQL Server">SQL</span>
                                @elseif($record->db_source === 'access')
                                    <span class="badge bg-info text-dark" title="Access">ACC</span>
                                @elseif($record->db_source === 'not_found')
                                    <span class="badge bg-warning text-dark" title="Non trovato">N/T</span>
                                @else
                                    <span class="badge bg-secondary">{{ $record->db_source }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>

<script src="/js/bootstrap.bundle.min.js"></script>
<script>
    const collapse = document.getElementById('detailsCollapse');
    const icon = document.querySelector('.toggle-icon');
    if (collapse && icon) {
        collapse.addEventListener('show.bs.collapse', () => {
            icon.style.transform = 'rotate(90deg)';
        });
        collapse.addEventListener('hide.bs.collapse', () => {
            icon.style.transform = 'rotate(0deg)';
        });
    }
</script>
</body>
</html>
