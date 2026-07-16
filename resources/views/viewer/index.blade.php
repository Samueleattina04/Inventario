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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Search --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('viewer.index') }}" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort', 'article_code') }}">
                <input type="hidden" name="dir"  value="{{ request('dir', 'asc') }}">
                <div class="flex-grow-1">
                    <input type="text" name="search" class="form-control"
                           placeholder="Cerca per codice articolo o lotto..."
                           value="{{ request('search') }}">
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Cerca</button>
                @if(request('search'))
                    <a href="{{ route('viewer.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                @endif
            </form>
        </div>
    </div>

    <div class="text-muted small mb-2">{{ $records->total() }} registrazioni trovate</div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-sm">
                <thead class="table-dark">
                    <tr>
                        @php
                            $curSort = request('sort', 'article_code');
                            $curDir  = request('dir', 'asc');
                            $search  = request('search');
                            function sortUrl(string $col, string $curSort, string $curDir, ?string $search): string {
                                $dir = ($curSort === $col && $curDir === 'asc') ? 'desc' : 'asc';
                                return route('viewer.index') . '?' . http_build_query(array_filter(['sort' => $col, 'dir' => $dir, 'search' => $search]));
                            }
                        @endphp
                        <th>
                            <a href="{{ sortUrl('id', $curSort, $curDir, $search) }}" class="text-white text-decoration-none">
                                ID <i class="bi bi-arrow-{{ $curSort === 'id' ? ($curDir === 'asc' ? 'up' : 'down') : 'down-up' }} small"></i>
                            </a>
                        </th>
                        <th>Data/Ora</th>
                        <th>Operatore</th>
                        <th>Magazzino / Area</th>
                        <th>
                            <a href="{{ sortUrl('article_code', $curSort, $curDir, $search) }}" class="text-white text-decoration-none">
                                Codice <i class="bi bi-arrow-{{ $curSort === 'article_code' ? ($curDir === 'asc' ? 'up' : 'down') : 'down-up' }} small"></i>
                            </a>
                        </th>
                        <th>Descrizione</th>
                        <th>UM</th>
                        <th>Lotto</th>
                        <th>Scadenza</th>
                        <th class="text-end">
                            <a href="{{ sortUrl('quantity', $curSort, $curDir, $search) }}" class="text-white text-decoration-none">
                                Quantità <i class="bi bi-arrow-{{ $curSort === 'quantity' ? ($curDir === 'asc' ? 'up' : 'down') : 'down-up' }} small"></i>
                            </a>
                        </th>
                        <th>DB</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                    <tr>
                        <td class="text-muted small">#{{ $record->id }}</td>
                        <td class="text-nowrap small">{{ $record->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $record->user?->name }}</td>
                        <td class="small">
                            <div class="fw-semibold">{{ $record->warehouse?->name }}</div>
                            <div class="text-muted">{{ $record->area?->name }}</div>
                        </td>
                        <td>
                            <code class="small">{{ $record->article_code }}</code>
                            @if($record->rettified)
                                <span class="badge bg-warning text-dark ms-1" title="Rettificata"><i class="bi bi-pencil-square"></i></span>
                            @endif
                        </td>
                        <td class="small">{{ Str::limit($record->description, 40) }}</td>
                        <td><span class="badge bg-light text-dark">{{ $record->um }}</span></td>
                        <td class="small text-muted">{{ $record->lot ?: '—' }}</td>
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
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                            Nessuna registrazione trovata.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($records->hasPages())
        <div class="card-footer bg-white">
            {{ $records->links() }}
        </div>
        @endif
    </div>
</div>

<script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>
