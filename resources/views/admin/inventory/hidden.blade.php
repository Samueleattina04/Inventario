@extends('layouts.admin')

@section('title', 'Registrazioni Nascoste')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventario</a></li>
    <li class="breadcrumb-item active">Nascoste</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-eye-slash me-2 text-secondary"></i>Registrazioni Nascoste</h4>
    <a href="{{ route('admin.inventory.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Torna all'inventario
    </a>
</div>

<div class="alert alert-secondary mb-4">
    <i class="bi bi-info-circle me-2"></i>
    Le registrazioni nascoste <strong>non vengono conteggiate</strong> nei totali, nelle statistiche e nell'export Excel. Rimangono nel database per tracciabilità.
</div>

<div class="text-muted small mb-2">
    {{ $records->total() }} registrazioni nascoste
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-secondary">
                <tr>
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
                <tr class="text-muted">
                    <td class="text-nowrap small">{{ $record->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $record->user?->name }}</td>
                    <td class="small">
                        <div class="fw-semibold">{{ $record->warehouse?->name }}</div>
                        <div class="text-muted">{{ $record->area?->name }}</div>
                    </td>
                    <td><code class="small">{{ $record->article_code }}</code></td>
                    <td class="small">{{ Str::limit($record->description, 40) }}</td>
                    <td><span class="badge bg-light text-dark">{{ $record->um }}</span></td>
                    <td class="small text-muted">{{ $record->lot }}</td>
                    <td class="small text-nowrap">
                        @if($record->expiry_date)
                            <span>{{ $record->expiry_date->format('d/m/Y') }}</span>
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
                        <form method="POST" action="{{ route('admin.inventory.unhide', $record) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Ripristina">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Ripristina
                            </button>
                        </form>
                        @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('admin.inventory.destroy', $record) }}" class="d-inline"
                              onsubmit="return confirm('Eliminare definitivamente la registrazione #{{ $record->id }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-4">Nessuna registrazione nascosta.</td></tr>
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
