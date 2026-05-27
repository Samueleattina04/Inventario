@extends('layouts.admin')

@section('title', 'Dettaglio Registrazione')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventario</a></li>
    <li class="breadcrumb-item active">#{{ $record->id }}</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-card-text me-2 text-primary"></i>Registrazione #{{ $record->id }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.inventory.edit', $record) }}" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil me-1"></i>Modifica
        </a>
        <form method="POST" action="{{ route('admin.inventory.destroy', $record) }}"
              onsubmit="return confirm('Eliminare questa registrazione?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Elimina
            </button>
        </form>
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Torna alla lista
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-primary text-white">
                <i class="bi bi-box-seam me-2"></i>Articolo
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Codice</th>
                        <td><code>{{ $record->article_code }}</code></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Descrizione</th>
                        <td>{{ $record->description ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">U.M.</th>
                        <td>{{ $record->um ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Lotto</th>
                        <td>{{ $record->lot ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Scadenza</th>
                        <td>
                            @if($record->expiry_date)
                                @php $exp = $record->expiry_date; @endphp
                                <span class="{{ $exp->isPast() ? 'text-danger fw-bold' : ($exp->diffInDays() < 30 ? 'text-warning fw-semibold' : 'text-success') }}">
                                    {{ $exp->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Quantità</th>
                        <td class="fw-bold fs-5">{{ number_format($record->quantity, 2, ',', '.') }}
                            @if($record->um) <small class="text-muted">{{ $record->um }}</small> @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">DB Provenienza</th>
                        <td>
                            @if(str_starts_with($record->db_source ?? '', 'sqlsrv'))
                                <span class="badge bg-primary">SQL Server</span>
                            @elseif($record->db_source === 'access')
                                <span class="badge bg-info text-dark">Access</span>
                            @elseif($record->db_source === 'not_found')
                                <span class="badge bg-warning text-dark">Non trovato</span>
                            @else
                                <span class="badge bg-secondary">{{ $record->db_source }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-secondary text-white">
                <i class="bi bi-info-circle me-2"></i>Dettagli Registrazione
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Operatore</th>
                        <td>{{ $record->user?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Data/Ora</th>
                        <td>{{ $record->created_at->format('d/m/Y H:i:s') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Magazzino</th>
                        <td>{{ $record->warehouse?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Area</th>
                        <td>{{ $record->area?->name ?? '—' }}</td>
                    </tr>
                    @if($record->sample_count)
                    <tr>
                        <th class="text-muted">Pezzi campione</th>
                        <td>{{ $record->sample_count }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Peso campione</th>
                        <td>{{ $record->sample_weight }} kg</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Peso totale</th>
                        <td>{{ $record->total_weight }} kg</td>
                    </tr>
                    @endif
                    <tr>
                        <th class="text-muted">Note</th>
                        <td>{{ $record->notes ?: '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
