@extends('layouts.admin')

@section('title', 'Modifica Registrazione')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventario</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.show', $record) }}">#{{ $record->id }}</a></li>
    <li class="breadcrumb-item active">Modifica</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2 text-warning"></i>Modifica Registrazione #{{ $record->id }}</h4>
    <a href="{{ route('admin.inventory.show', $record) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Annulla
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger mb-3">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.inventory.update', $record) }}">
    @csrf @method('PUT')

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-box-seam me-2"></i>Dati Articolo
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Codice Articolo *</label>
                        <input type="text" name="article_code" class="form-control @error('article_code') is-invalid @enderror"
                               value="{{ old('article_code', $record->article_code) }}" required>
                        @error('article_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descrizione</label>
                        <input type="text" name="description" class="form-control"
                               value="{{ old('description', $record->description) }}">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">U.M.</label>
                            <input type="text" name="um" class="form-control"
                                   value="{{ old('um', $record->um) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Quantità *</label>
                            <input type="number" name="quantity" step="any" min="0"
                                   class="form-control @error('quantity') is-invalid @enderror"
                                   value="{{ old('quantity', $record->quantity) }}" required>
                            @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Lotto</label>
                            <input type="text" name="lot" class="form-control"
                                   value="{{ old('lot', $record->lot) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Scadenza</label>
                            <input type="date" name="expiry_date" class="form-control"
                                   value="{{ old('expiry_date', $record->expiry_date?->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $record->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Info Registrazione (sola lettura)
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
                        <tr>
                            <th class="text-muted">DB Provenienza</th>
                            <td>{{ $record->db_source_label }}</td>
                        </tr>
                    </table>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Operatore, data, magazzino e area non sono modificabili dall'admin.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-warning fw-bold">
            <i class="bi bi-floppy-fill me-2"></i>Salva Modifiche
        </button>
        <a href="{{ route('admin.inventory.show', $record) }}" class="btn btn-outline-secondary">
            Annulla
        </a>
    </div>
</form>
@endsection
