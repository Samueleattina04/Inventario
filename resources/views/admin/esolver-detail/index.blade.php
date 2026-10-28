@extends('layouts.admin')

@section('title', 'Rettifica Export')
@section('breadcrumb')
    <li class="breadcrumb-item active">Rettifica Export</li>
@endsection

@section('extra-styles')
.badge-only-esolver { background:#6c757d; }
.badge-only-count   { background:#0d6efd; }
.badge-diff         { background:#dc3545; }
.table-sm td, .table-sm th { font-size:0.83rem; }
.rettifica-zero { color:#6c757d; }
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Rettifica Export</h4>
</div>

<div class="row g-3 mb-4">
    {{-- Import --}}
    <div class="col-12 col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-primary text-white">
                <i class="bi bi-upload me-2"></i>Importa file Esolver APP (.xlsx)
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.esolver-detail.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Colonne: Mag, Articolo, Descrizione, Lotto, UdM, Giacenza</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-cloud-upload me-1"></i>Importa
                    </button>
                </form>
                <hr>
                <form method="POST" action="{{ route('admin.esolver-detail.import-categories') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-diagram-3 me-1"></i>Categorie articoli (.xlsx)
                        @if($categoryCount > 0)
                            <span class="badge bg-success ms-1">{{ number_format($categoryCount, 0, ',', '.') }} articoli</span>
                        @endif
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-cloud-upload me-1"></i>Importa</button>
                    </div>
                    <div class="form-text">Colonne: Codice, Descrizione, Settore, Linea, Macrofamiglia, Famiglia, Classe inv</div>
                </form>
            </div>
        </div>
    </div>

    {{-- Status + Export --}}
    <div class="col-12 col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-secondary text-white">
                <i class="bi bi-info-circle me-2"></i>Stato & Export
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-3">
                @if($count > 0)
                    <i class="bi bi-check-circle-fill text-success fs-1 mb-2"></i>
                    <div class="fw-bold fs-5">{{ number_format($count, 0, ',', '.') }} righe caricate</div>
                    @if($lastUpdate)
                        <div class="text-muted small mb-3">Ultimo aggiornamento: {{ $lastUpdate->format('d/m/Y H:i') }}</div>
                    @endif
                    <a href="{{ route('admin.esolver-detail.export', $query) }}" class="btn btn-success btn-lg">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Scarica file rettifica .csv
                    </a>
                @else
                    <i class="bi bi-exclamation-circle text-warning fs-1 mb-2"></i>
                    <div class="text-muted">Importa il file Esolver APP per abilitare il confronto e l'export.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($count > 0)
{{-- Filters (above the table's sticky header so the category dropdowns are not covered) --}}
<div class="card border-0 shadow-sm mb-3 position-relative" style="z-index:1030;">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.esolver-detail.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <label class="text-muted small mb-0">Magazzino:</label>
            <select name="mag" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="all" {{ $magFilter === 'all' ? 'selected' : '' }}>Tutti i magazzini</option>
                @foreach($availableMags as $mag)
                    <option value="{{ $mag }}" {{ $magFilter === $mag ? 'selected' : '' }}>
                        {{ $mag }}{{ isset($magMap[$mag]) ? ' — ' . $magMap[$mag] : '' }}
                    </option>
                @endforeach
            </select>
            <div class="vr"></div>
            <span class="text-muted small">Stato:</span>
            @foreach(['all'=>'Tutti','diff'=>'Solo differenze','only_count'=>'Solo in conta','only_esolver'=>'Solo in Esolver'] as $val => $label)
                <a href="{{ route('admin.esolver-detail.index', array_merge($query, ['filter' => $val])) }}"
                   class="btn btn-sm {{ $filter === $val ? 'btn-dark' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach
            <div class="vr"></div>
            <div class="input-group input-group-sm" style="width:220px;">
                <input type="text" name="search" class="form-control" placeholder="Cerca articolo…" value="{{ $search ?? '' }}">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                @if(!empty($search))
                    <a href="{{ route('admin.esolver-detail.index', array_diff_key($query, ['search' => 1])) }}" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
                @endif
            </div>
            <div class="input-group input-group-sm" style="width:220px;">
                <input type="text" name="search_desc" class="form-control" placeholder="Cerca descrizione…" value="{{ $searchDesc ?? '' }}">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                @if(!empty($searchDesc))
                    <a href="{{ route('admin.esolver-detail.index', array_diff_key($query, ['search_desc' => 1])) }}" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
                @endif
            </div>
            <span class="ms-auto text-muted small">{{ $totalRows }} righe</span>
            <a href="{{ route('admin.esolver-detail.export-excel', $query) }}"
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Esporta Excel
            </a>

            @if(!empty($categoryOptions['settore']))
            <div class="w-100"></div>
            <span class="text-muted small"><i class="bi bi-diagram-3 me-1"></i>Categoria:</span>
            @foreach(['settore' => 'Settore', 'linea' => 'Linea', 'macrofamiglia' => 'Macrofamiglia', 'famiglia' => 'Famiglia'] as $param => $label)
                <div class="dropdown category-level" data-param="{{ $param }}" data-label="{{ $label }}">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <span class="category-summary">{{ $label }}: tutte</span>
                    </button>
                    <div class="dropdown-menu p-2 shadow" style="width:340px;">
                        <input type="text" class="form-control form-control-sm mb-2 category-search" placeholder="Filtra {{ strtolower($label) }}…">
                        <div class="d-flex gap-1 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 category-all">Seleziona tutte</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 category-none">Deseleziona tutte</button>
                        </div>
                        <div class="category-list" style="max-height:300px; overflow-y:auto;"></div>
                    </div>
                </div>
            @endforeach
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Applica</button>
            @if(collect($categoryFilter)->flatten()->isNotEmpty())
                <a href="{{ route('admin.esolver-detail.index', array_diff_key($query, $categoryFilter)) }}" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-x"></i> Rimuovi categoria
                </a>
            @endif
            <div class="w-100 small text-muted">
                Nessuna spunta = tutte. Un livello inferiore restringe solo i rami spuntati sopra
                (es. Settore A + B, e una Linea di B: resta tutto A e solo quella Linea di B).
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Comparison table --}}
<div class="card border-0 shadow-sm">
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">Pagina {{ $rows->currentPage() }} di {{ $rows->lastPage() }}</span>
        {{ $rows->links('pagination::bootstrap-5') }}
    </div>
    @endif
    <div class="table-responsive" style="max-height:65vh; overflow-y:auto;">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Mag</th>
                    <th>Magazzino</th>
                    <th>Articolo Esolver</th>
                    <th>Articolo OMNI</th>
                    <th>Lotto</th>
                    <th>Descrizione</th>
                    <th class="text-end">Giacenza Esolver</th>
                    <th class="text-end">Conta fisica</th>
                    <th class="text-end">Rettifica export</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr class="{{ $row->is_diff ? 'table-warning' : '' }}">
                    <td class="small fw-semibold">{{ $row->mag }}</td>
                    <td class="small text-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $row->warehouse }}">{{ $row->warehouse }}</td>
                    <td class="fw-semibold text-nowrap"><code>{{ $row->esolver_article ?: '—' }}</code></td>
                    <td class="fw-semibold text-nowrap"><code>{{ $row->omni_article ?: '—' }}</code></td>
                    <td class="small text-nowrap"><code>{{ $row->lot ?: '—' }}</code></td>
                    <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $row->description }}">{{ $row->description }}</td>
                    <td class="text-end">
                        @if($row->esolver_qty !== null)
                            {{ number_format($row->esolver_qty, 2, ',', '.') }}
                            @if($row->um) <small class="text-muted">{{ $row->um }}</small> @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($row->count_qty !== null)
                            <span class="fw-semibold">{{ number_format($row->count_qty, 2, ',', '.') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold {{ $row->rettifica == 0 ? 'rettifica-zero' : ($row->rettifica < 0 ? 'text-danger' : 'text-primary') }}">
                        {{ number_format($row->rettifica, 2, ',', '.') }}
                    </td>
                    <td>
                        @if($row->only_esolver)
                            <span class="badge bg-secondary">Solo Esolver → 0</span>
                        @elseif($row->only_count)
                            <span class="badge bg-primary">Solo Conta</span>
                        @elseif(round((float)$row->esolver_qty,4) == round((float)$row->count_qty,4))
                            <span class="badge bg-success">Quadra</span>
                        @else
                            <span class="badge bg-danger">Differenza</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">Nessuna riga per questo filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
(() => {
    const options  = @json($categoryOptions);
    const selected = @json($categoryFilter);
    const levels   = [...document.querySelectorAll('.category-level')];
    if (!levels.length) return;

    const esc = t => String(t ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

    // Build the checkbox list of every level once; visibility is recomputed on each change
    levels.forEach(level => {
        const param = level.dataset.param;
        const chosen = new Set(selected[param] || []);
        level.querySelector('.category-list').innerHTML = (options[param] || []).map(o => `
            <label class="form-check d-block small mb-1 category-item" data-parent="${esc(o.parent)}" data-name="${esc(o.name).toLowerCase()}">
                <input class="form-check-input" type="checkbox" name="${param}[]" value="${esc(o.code)}" ${chosen.has(o.code) ? 'checked' : ''}>
                <span class="form-check-label">${esc(o.name)}</span>
            </label>`).join('');
    });

    const items   = level => [...level.querySelectorAll('.category-item')];
    const checked = level => items(level).filter(i => i.querySelector('input').checked);

    // A child is offered only if its parent is ticked (or, with nothing ticked above, if its parent is offered)
    function refresh() {
        let allowedParents = null; // null = no restriction (top level)
        levels.forEach(level => {
            items(level).forEach(item => {
                const ok = allowedParents === null || allowedParents.has(item.dataset.parent);
                item.classList.toggle('d-none', !ok);
                if (!ok) item.querySelector('input').checked = false;
            });
            const visible = items(level).filter(i => !i.classList.contains('d-none'));
            const ticked  = checked(level);
            allowedParents = new Set((ticked.length ? ticked : visible).map(i => i.querySelector('input').value));
            level.querySelector('.category-summary').textContent =
                level.dataset.label + ': ' + (ticked.length === 0 ? 'tutte' : ticked.length + ' selezionat' + (ticked.length === 1 ? 'a' : 'e'));
            level.querySelector('button').classList.toggle('btn-outline-secondary', ticked.length === 0);
            level.querySelector('button').classList.toggle('btn-secondary', ticked.length > 0);
        });
    }

    levels.forEach(level => {
        level.querySelector('.category-list').addEventListener('change', refresh);
        level.querySelector('.category-search').addEventListener('input', e => {
            const q = e.target.value.trim().toLowerCase();
            items(level).forEach(i => i.style.display = (!q || i.dataset.name.includes(q)) ? '' : 'none');
        });
        const visibleInputs = () => items(level)
            .filter(i => !i.classList.contains('d-none') && i.style.display !== 'none')
            .map(i => i.querySelector('input'));
        level.querySelector('.category-all').addEventListener('click', () => { visibleInputs().forEach(c => c.checked = true); refresh(); });
        level.querySelector('.category-none').addEventListener('click', () => { visibleInputs().forEach(c => c.checked = false); refresh(); });
    });

    refresh();
})();
</script>
@endsection
