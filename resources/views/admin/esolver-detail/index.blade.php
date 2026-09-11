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
                    <a href="{{ route('admin.esolver-detail.export') }}" class="btn btn-success btn-lg">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Scarica file rettifica .txt
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
@php
    $warehousesWithoutMag = \App\Models\Warehouse::where('active', true)->whereNull('mag_code')->count();
@endphp
@if($warehousesWithoutMag > 0)
<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>{{ $warehousesWithoutMag }} magazzin{{ $warehousesWithoutMag == 1 ? 'o' : 'i' }} senza Codice Mag Esolver.</strong>
    Le registrazioni di quei magazzini non verranno abbinate alle giacenze Esolver.
    <a href="{{ route('admin.warehouses.index') }}" class="alert-link ms-1">Configura ora →</a>
</div>
@endif
{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.esolver-detail.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted small me-1">Mostra:</span>
            @foreach(['all'=>'Tutti','diff'=>'Solo differenze','only_count'=>'Solo in conta','only_esolver'=>'Solo in Esolver'] as $val => $label)
                <button type="submit" name="filter" value="{{ $val }}"
                        class="btn btn-sm {{ $filter === $val ? 'btn-dark' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </button>
            @endforeach
            <span class="ms-auto text-muted small">
                {{ $rows->count() }} righe mostrate
                @if($totalRows > $rows->count())
                    <span class="text-warning fw-semibold">(su {{ $totalRows }} totali — usa i filtri per restringere)</span>
                @endif
            </span>
        </form>
    </div>
</div>

{{-- Comparison table --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive" style="max-height:65vh; overflow-y:auto;">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Mag</th>
                    <th>Magazzino</th>
                    <th>Articolo</th>
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
                    <td class="fw-semibold text-nowrap"><code>{{ $row->article_code }}</code></td>
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
                    <td class="text-end fw-bold {{ $row->rettifica == 0 && $row->only_esolver ? 'rettifica-zero' : 'text-primary' }}">
                        {{ number_format($row->rettifica, 2, ',', '.') }}
                    </td>
                    <td>
                        @if($row->only_esolver)
                            <span class="badge bg-secondary">Solo Esolver → 0</span>
                        @elseif($row->only_count)
                            <span class="badge bg-primary">Solo conta</span>
                        @elseif(round((float)$row->esolver_qty,4) == round((float)$row->count_qty,4))
                            <span class="badge bg-success">Quadra</span>
                        @else
                            <span class="badge bg-danger">Differenza</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Nessuna riga per questo filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
