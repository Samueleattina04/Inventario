@extends('layouts.app')

@section('title', 'Storico Registrazioni')

@section('extra-styles')
.record-row:hover { background-color: #f8f9fa; }
.badge-source { font-size: 0.7rem; }
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-10">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-clock-history me-2 text-primary"></i>Le mie registrazioni
            </h5>
            <a href="{{ route('scan') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-qr-code-scan me-1"></i>Nuova scansione
            </a>
        </div>

        @if($records->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4"></i>
                    <p class="mt-3">Nessuna registrazione ancora effettuata.</p>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Data/Ora</th>
                                    <th>Magazzino / Area</th>
                                    <th>Codice</th>
                                    <th class="d-none d-md-table-cell">Descrizione</th>
                                    <th>Lotto</th>
                                    <th class="d-none d-sm-table-cell">Scadenza</th>
                                    <th class="text-end pe-3">Quantità</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($records as $record)
                                    <tr class="record-row">
                                        <td class="ps-3 text-nowrap">
                                            <span class="fw-semibold">{{ $record->created_at->format('d/m/Y') }}</span>
                                            <br><small class="text-muted">{{ $record->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <span class="text-nowrap">{{ $record->warehouse?->name ?? '—' }}</span>
                                            @if($record->area)
                                                <br><small class="text-muted">{{ $record->area->name }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary">{{ $record->article_code }}</span>
                                            @if($record->db_source === 'not_found')
                                                <br><span class="badge bg-danger badge-source">Non in DB</span>
                                            @endif
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <small>{{ $record->description ?: '—' }}</small>
                                        </td>
                                        <td>
                                            {{ $record->lot ?: '—' }}
                                        </td>
                                        <td class="d-none d-sm-table-cell">
                                            @if($record->expiry_date)
                                                @php $exp = \Carbon\Carbon::parse($record->expiry_date); @endphp
                                                <span class="small {{ $exp->isPast() ? 'text-danger fw-semibold' : ($exp->diffInDays() < 30 ? 'text-warning fw-semibold' : '') }}">
                                                    {{ $exp->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 fw-bold">
                                            {{ rtrim(rtrim(number_format((float)$record->quantity, 4, '.', ''), '0'), '.') }}
                                            @if($record->um)
                                                <small class="text-muted fw-normal">{{ $record->um }}</small>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-center">
                {{ $records->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
