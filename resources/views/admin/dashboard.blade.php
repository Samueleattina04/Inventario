@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
    <a href="{{ route('admin.inventory.export') }}" class="btn btn-success">
        <i class="bi bi-file-earmark-excel me-1"></i>Esporta Excel
    </a>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-5 fw-bold text-primary" id="todayCount">{{ $todayCount }}</div>
                <div class="text-muted small mt-1">Registrazioni oggi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-5 fw-bold text-success" id="totalCount">{{ $totalCount }}</div>
                <div class="text-muted small mt-1">Totale complessivo</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-5 fw-bold text-info">{{ $activeOperators }}</div>
                <div class="text-muted small mt-1">Operatori attivi</div>
            </div>
        </div>
    </div>
</div>

{{-- By warehouse today --}}
@if($byWarehouse->count())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-building me-2"></i>Registrazioni oggi per magazzino
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Magazzino</th>
                        <th class="text-end">Registrazioni oggi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byWarehouse as $wh)
                        @if($wh->inventory_records_count > 0)
                        <tr>
                            <td>{{ $wh->name }} <small class="text-muted">({{ $wh->code }})</small></td>
                            <td class="text-end">
                                <span class="badge bg-primary rounded-pill">{{ $wh->inventory_records_count }}</span>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Last records table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-clock-history me-2"></i>Ultime registrazioni</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="lastRecordsTable">
                <thead class="table-light">
                    <tr>
                        <th>Data/Ora</th>
                        <th>Operatore</th>
                        <th>Articolo</th>
                        <th>Magazzino/Area</th>
                        <th class="text-end">Qtà</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lastRecords as $record)
                    <tr>
                        <td class="text-nowrap">
                            <small>{{ $record->created_at?->format('d/m/Y H:i') }}</small>
                        </td>
                        <td>{{ $record->user?->name }}</td>
                        <td>
                            <div class="fw-semibold">{{ $record->article_code }}</div>
                            <small class="text-muted">{{ Str::limit($record->description, 30) }}</small>
                        </td>
                        <td>
                            <small>{{ $record->warehouse?->name }} / {{ $record->area?->name }}</small>
                        </td>
                        <td class="text-end fw-bold">
                            {{ number_format($record->quantity, 2, ',', '.') }}
                            @if($record->um)
                                <small class="text-muted">{{ $record->um }}</small>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-center">
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-sm btn-outline-primary">
            Vedi tutto l'inventario
        </a>
    </div>
</div>
@endsection

@endsection
