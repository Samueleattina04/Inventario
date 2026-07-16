@extends('layouts.admin')

@section('title', 'Giacenze Esolver')
@section('breadcrumb')
    <li class="breadcrumb-item active">Giacenze Esolver</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-database-up me-2 text-primary"></i>Giacenze Esolver (riferimento)</h4>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle me-2"></i>
    Carica l'export Excel da Esolver con le giacenze <strong>al giorno precedente l'inventario</strong>.
    I dati vengono usati nel pannello di consultazione per confrontare le giacenze Esolver con il conteggio inventario.
    <br><small class="text-muted">Ogni importazione sostituisce completamente i dati precedenti.</small>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold bg-primary text-white">
                <i class="bi bi-upload me-2"></i>Importa file Excel
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.esolver.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File Excel Esolver (.xlsx)</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Il file deve avere le colonne: Articolo, Descrizione, UdM, Giacenza.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-cloud-upload me-1"></i>Importa
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-secondary text-white">
                <i class="bi bi-info-circle me-2"></i>Stato attuale
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-5">
                @if($count > 0)
                    <i class="bi bi-check-circle-fill text-success display-4 mb-3"></i>
                    <div class="fw-bold fs-4">{{ number_format($count, 0, ',', '.') }} articoli caricati</div>
                    @if($lastUpdate)
                        <div class="text-muted small mt-1">Ultimo aggiornamento: {{ $lastUpdate->format('d/m/Y H:i') }}</div>
                    @endif
                @else
                    <i class="bi bi-exclamation-circle text-warning display-4 mb-3"></i>
                    <div class="fw-bold fs-5 text-muted">Nessun dato caricato</div>
                    <div class="text-muted small mt-1">Importa il file Excel per abilitare il confronto nel pannello di consultazione.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
