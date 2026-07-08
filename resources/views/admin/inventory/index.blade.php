@extends('layouts.admin')

@section('title', 'Inventario')
@section('breadcrumb')
    <li class="breadcrumb-item active">Inventario</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Registro Inventario</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.inventory.grouped') }}?{{ http_build_query(request()->only(['date_from','time_from','date_to','time_to','warehouse_id','area_id','user_id','source','search','um','rettified'])) }}"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-layers me-1"></i>Per Articolo
        </a>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.inventory.export') }}?{{ http_build_query(request()->only(['date_from','time_from','date_to','time_to','warehouse_id','area_id','user_id','source','search','um','rettified'])) }}"
           class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Esporta Excel
        </a>
        @endif
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
        <a href="{{ route('admin.inventory.index') }}?{{ http_build_query(array_merge(request()->only(['date_from','date_to','warehouse_id','area_id','user_id','search']), ['source' => $val])) }}"
           class="btn btn-sm {{ $activeSource === $val ? $opt['class'] : 'btn-outline-'.explode('-',$opt['class'])[1] }}">
            {{ $opt['label'] }}
        </a>
    @endforeach
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="row g-3 align-items-end">
            <input type="hidden" name="source" value="{{ request('source') }}">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small">Codice / Lotto</label>
                <input type="text" name="search" class="form-control" placeholder="Cerca codice articolo, lotto o ID..."
                    value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Dal</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                <input type="time" name="time_from" class="form-control mt-1" value="{{ request('time_from') }}" placeholder="HH:MM">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Al</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                <input type="time" name="time_to" class="form-control mt-1" value="{{ request('time_to') }}" placeholder="HH:MM">
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
                <label class="form-label fw-semibold small">Area</label>
                <select name="area_id" class="form-select">
                    <option value="">Tutte</option>
                    @foreach($areas as $a)
                        <option value="{{ $a->id }}" {{ request('area_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
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
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">UM</label>
                <select name="um" class="form-select">
                    <option value="">Tutte</option>
                    @foreach($ums as $um)
                        <option value="{{ $um }}" {{ request('um') == $um ? 'selected' : '' }}>{{ $um }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Calcolatore peso</label>
                <select name="has_calc" class="form-select">
                    <option value="">Tutti</option>
                    <option value="1" {{ request('has_calc') === '1' ? 'selected' : '' }}>Con calcolatore</option>
                    <option value="0" {{ request('has_calc') === '0' ? 'selected' : '' }}>Senza calcolatore</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Rettifica</label>
                <select name="rettified" class="form-select">
                    <option value="">Tutte</option>
                    <option value="1" {{ request('rettified') === '1' ? 'selected' : '' }}>Solo rettificate</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtra</button>
                <a href="{{ route('admin.inventory.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Results count --}}
<div class="text-muted small mb-2">
    {{ $records->total() }} record trovati
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Data/Ora</th>
                    <th>Operatore</th>
                    <th>Magazzino / Area</th>
                    <th>Codice</th>
                    <th>Descrizione</th>
                    <th>UM</th>
                    <th>Lotto</th>
                    <th>Scadenza</th>
                    <th class="text-end">Quantità</th>
                    <th>DB</th>
                    <th></th>
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
                            <span class="badge bg-warning text-dark ms-1" title="Registrazione rettificata"><i class="bi bi-pencil-square"></i> Rett.</span>
                        @endif
                    </td>
                    <td class="small">{{ Str::limit($record->description, 40) }}</td>
                    <td><span class="badge bg-light text-dark">{{ $record->um }}</span></td>
                    <td class="small text-muted">{{ $record->lot }}</td>
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
                    <td class="text-nowrap">
                        <a href="{{ route('admin.inventory.show', $record) }}" class="btn btn-sm btn-outline-primary" title="Dettaglio">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('admin.inventory.edit', $record) }}" class="btn btn-sm btn-outline-warning" title="Modifica">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('admin.inventory.hide', $record) }}" class="d-inline"
                              onsubmit="return confirm('Nascondere la registrazione #{{ $record->id }}?')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Nascondi">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </form>
                        @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('admin.inventory.destroy', $record) }}" class="d-inline"
                              onsubmit="return confirm('Eliminare la registrazione #{{ $record->id }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-4">Nessun record trovato.</td></tr>
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
@endsection

