@extends('layouts.admin')

@section('title', $warehouse ? 'Modifica Magazzino' : 'Nuovo Magazzino')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.warehouses.index') }}">Magazzini/Aree</a></li>
    <li class="breadcrumb-item active">{{ $warehouse ? 'Modifica' : 'Nuovo' }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-12 col-md-8 col-lg-6">
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-building me-2 text-primary"></i>
            {{ $warehouse ? 'Modifica Magazzino' : 'Nuovo Magazzino' }}
        </h5>
    </div>
    <div class="card-body p-4">
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ $warehouse ? route('admin.warehouses.update', $warehouse) : route('admin.warehouses.store') }}">
            @csrf
            @if($warehouse) @method('PUT') @endif
            <div class="mb-3">
                <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                       value="{{ old('name', $warehouse?->name) }}" placeholder="Es. Magazzino Centrale">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Codice <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control" required
                       value="{{ old('code', $warehouse?->code) }}" placeholder="Es. MAG-A" style="text-transform:uppercase">
                <div class="form-text">Identificativo breve univoco.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Codice Mag Esolver</label>
                <input type="text" name="mag_code" class="form-control"
                       value="{{ old('mag_code', $warehouse?->mag_code) }}" placeholder="Es. 01, 06, 61">
                <div class="form-text">Codice magazzino usato nel file Esolver APP (colonna "Mag"). Necessario per il confronto rettifica.</div>
            </div>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="active" value="1" id="active"
                       {{ old('active', $warehouse?->active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="active">Magazzino attivo</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-save me-1"></i>{{ $warehouse ? 'Salva Modifiche' : 'Crea Magazzino' }}
                </button>
                <a href="{{ route('admin.warehouses.index') }}" class="btn btn-secondary">Annulla</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
