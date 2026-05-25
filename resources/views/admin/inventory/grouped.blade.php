@extends('layouts.admin')

@section('title', 'Inventario per Articolo')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventario</a></li>
    <li class="breadcrumb-item active">Per Articolo</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-layers me-2 text-primary"></i>Inventario per Articolo</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.inventory.index') }}?{{ http_build_query(request()->only(['date_from','date_to','warehouse_id','user_id','source'])) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>Vista lista
        </a>
        <a href="{{ route('admin.inventory.export') }}?{{ http_build_query(request()->only(['date_from','date_to','warehouse_id','user_id','source'])) }}"
           class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Esporta Excel
        </a>
    </div>
</div>

{{-- Source quick-filter buttons --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
    @php $activeSource = request('source', ''); @endphp
    @foreach([
        ''          => ['label' => 'Tutti',       'class' => 'btn-secondary'],
        'sqlsrv'    => ['label' => 'SQL Server',   'class' => 'btn-primary'],
        'access'    => ['label' => 'Access',       'class' => 'btn-info text-dark'],
        'not_found' => ['label' => 'Non trovati',  'class' => 'btn-warning text-dark'],
    ] as $val => $opt)
        <a href="{{ route('admin.inventory.grouped') }}?{{ http_build_query(array_merge(request()->only(['date_from','date_to','warehouse_id','user_id']), ['source' => $val])) }}"
           class="btn btn-sm {{ $activeSource === $val ? $opt['class'] : 'btn-outline-'.explode('-',$opt['class'])[1] }}">
            {{ $opt['label'] }}
        </a>
    @endforeach
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.inventory.grouped') }}" class="row g-3 align-items-end">
            <input type="hidden" name="source" value="{{ request('source') }}">
            <div class="col-6 col-md-3">
                <label class="form-label fw-semibold small">Dal</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fw-semibold small">Al</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Magazzino</label>
                <select name="warehouse_id" class="form-select">
                    <option value="">Tutti</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Operatore</label>
                <select name="user_id" class="form-select">
                    <option value="">Tutti</option>
                    @foreach($operators as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtra</button>
                <a href="{{ route('admin.inventory.grouped') }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Results count --}}
<div class="text-muted small mb-2">
    {{ $articles->total() }} articoli trovati
</div>

{{-- Article accordion list --}}
<div class="accordion" id="articleAccordion">
    @forelse($articles as $article)
    @php
        $lots    = $lotDetails[$article->article_code] ?? collect();
        $collapseId = 'art_' . md5($article->article_code);
    @endphp
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-header bg-white py-2 px-3 d-flex align-items-center justify-content-between"
             role="button"
             data-bs-toggle="collapse"
             data-bs-target="#{{ $collapseId }}"
             aria-expanded="false">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <code class="fw-bold text-primary fs-6">{{ $article->article_code }}</code>
                <span class="text-muted small">{{ Str::limit($article->description, 50) }}</span>
                @if($article->um)
                    <span class="badge bg-light text-dark border">{{ $article->um }}</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fw-bold text-success fs-5">{{ number_format($article->total_qty, 2, ',', '.') }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ $article->record_count }} scan</div>
                </div>
                <i class="bi bi-chevron-down text-muted"></i>
            </div>
        </div>
        <div class="collapse" id="{{ $collapseId }}">
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Data/Ora</th>
                            <th>Operatore</th>
                            <th>Magazzino / Area</th>
                            <th>Lotto</th>
                            <th class="text-end pe-3">Quantità</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lots as $lot)
                        <tr>
                            <td class="text-nowrap small ps-3">{{ $lot->created_at->format('d/m/Y H:i') }}</td>
                            <td class="small">{{ $lot->user?->name }}</td>
                            <td class="small">
                                <div class="fw-semibold">{{ $lot->warehouse?->name }}</div>
                                <div class="text-muted">{{ $lot->area?->name }}</div>
                            </td>
                            <td class="small text-muted font-monospace">{{ $lot->lot }}</td>
                            <td class="text-end fw-bold pe-3">{{ number_format($lot->quantity, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end ps-3">Totale</td>
                            <td class="text-end text-success pe-3">{{ number_format($article->total_qty, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">Nessun articolo trovato.</div>
    @endforelse
</div>

@if($articles->hasPages())
<div class="mt-3">
    {{ $articles->links() }}
</div>
@endif
@endsection
