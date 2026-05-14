@extends('layouts.admin')

@section('title', isset($area) ? 'Modifica Area' : 'Nuova Area')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.warehouses.index') }}">Magazzini/Aree</a></li>
    <li class="breadcrumb-item active">{{ isset($area) ? 'Modifica Area' : 'Nuova Area' }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-layers me-2"></i>
                    {{ isset($area) ? 'Modifica Area: ' . $area->name : 'Nuova Area' }}
                </h5>
            </div>
            <div class="card-body p-4">

                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST"
                    action="{{ isset($area)
                        ? route('admin.warehouses.areas.update', [$warehouse, $area])
                        : route('admin.warehouses.areas.store', $warehouse) }}">
                    @csrf
                    @if(isset($area))
                        @method('PUT')
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Magazzino</label>
                        <input type="text" class="form-control" value="{{ $warehouse->name }}" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nome Area *</label>
                        <input type="text" id="name" name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', isset($area) ? $area->name : '') }}"
                            placeholder="es. Area 1"
                            required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold">Codice *</label>
                        <input type="text" id="code" name="code"
                            class="form-control @error('code') is-invalid @enderror"
                            value="{{ old('code', isset($area) ? $area->code : '') }}"
                            placeholder="es. A1"
                            style="text-transform:uppercase"
                            required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="has_weight_calculator"
                                name="has_weight_calculator" value="1"
                                {{ old('has_weight_calculator', isset($area) && $area->has_weight_calculator ? '1' : '0') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="has_weight_calculator">
                                <i class="bi bi-calculator me-1"></i>Abilita calcolatore peso
                            </label>
                            <div class="form-text">Se abilitato, nella schermata di inserimento comparirà il calcolatore peso.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="active" name="active"
                                value="1"
                                {{ old('active', isset($area) ? ($area->active ? '1' : '0') : '1') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="active">Area attiva</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-fill">
                            <i class="bi bi-floppy me-1"></i>
                            {{ isset($area) ? 'Aggiorna Area' : 'Crea Area' }}
                        </button>
                        <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline-secondary">
                            Annulla
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
