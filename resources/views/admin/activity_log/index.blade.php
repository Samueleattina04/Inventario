@extends('layouts.admin')

@section('title', 'Log Attività')
@section('breadcrumb')
    <li class="breadcrumb-item active">Log Attività</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Log Attività</h4>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.activity_log.index') }}" class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Dal</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label fw-semibold small">Al</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fw-semibold small">Utente</label>
                <select name="user_id" class="form-select">
                    <option value="">Tutti</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fw-semibold small">Operazione</label>
                <select name="action" class="form-select">
                    <option value="">Tutti</option>
                    <option value="scan_save"    {{ request('action') === 'scan_save'    ? 'selected' : '' }}>Registrazione inventario</option>
                    <option value="admin_edit"   {{ request('action') === 'admin_edit'   ? 'selected' : '' }}>Modifica</option>
                    <option value="admin_delete" {{ request('action') === 'admin_delete' ? 'selected' : '' }}>Eliminazione</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtra</button>
                <a href="{{ route('admin.activity_log.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
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

<div class="text-muted small mb-2">
    {{ $logs->total() }} eventi trovati
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-dark">
                <tr>
                    <th>Data/Ora</th>
                    <th>Utente</th>
                    <th>Ruolo</th>
                    <th>Operazione</th>
                    <th>Dettaglio</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td>
                        @php $role = $log->user?->role ?? ''; @endphp
                        @if($role === 'admin')
                            <span class="badge bg-danger">Admin</span>
                        @elseif($role === 'backoffice')
                            <span class="badge bg-warning text-dark">Backoffice</span>
                        @elseif($role === 'operator')
                            <span class="badge bg-secondary">Operatore</span>
                        @else
                            <span class="badge bg-light text-dark">{{ $role }}</span>
                        @endif
                    </td>
                    <td>
                        @if($log->action === 'scan_save')
                            <span class="badge bg-success">{{ $log->action_label }}</span>
                        @elseif($log->action === 'admin_edit')
                            <span class="badge bg-primary">{{ $log->action_label }}</span>
                        @elseif($log->action === 'admin_delete')
                            <span class="badge bg-danger">{{ $log->action_label }}</span>
                        @else
                            <span class="badge bg-secondary">{{ $log->action_label }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="small text-muted">{{ $log->description }}</div>
                        @if($log->old_values)
                        <div class="mt-1">
                            <button class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#changes-{{ $log->id }}"
                                    aria-expanded="false">
                                <i class="bi bi-diff me-1"></i>Modifiche
                            </button>
                            <div class="collapse mt-2" id="changes-{{ $log->id }}">
                                @php
                                    $fieldLabels = [
                                        'article_code' => 'Codice',
                                        'description'  => 'Descrizione',
                                        'um'           => 'UM',
                                        'lot'          => 'Lotto',
                                        'expiry_date'  => 'Scadenza',
                                        'quantity'     => 'Quantità',
                                        'notes'        => 'Note',
                                    ];
                                    $oldVals = $log->old_values ?? [];
                                    $newVals = $log->new_values ?? [];
                                    $changedFields = array_filter(array_keys($fieldLabels), function($key) use ($oldVals, $newVals) {
                                        return ($oldVals[$key] ?? null) != ($newVals[$key] ?? null);
                                    });
                                @endphp
                                @if(count($changedFields) > 0)
                                <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Campo</th>
                                            <th>Valore precedente</th>
                                            <th>Valore nuovo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($changedFields as $field)
                                        <tr>
                                            <td class="fw-semibold">{{ $fieldLabels[$field] }}</td>
                                            <td class="text-danger">{{ $oldVals[$field] ?? '—' }}</td>
                                            <td class="text-success">{{ $newVals[$field] ?? '—' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @else
                                <p class="text-muted small mb-0">Nessuna modifica rilevata.</p>
                                @endif
                            </div>
                        </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Nessun evento trovato.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-white">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
