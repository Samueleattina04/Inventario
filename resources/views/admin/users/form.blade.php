@extends('layouts.admin')

@section('title', $user ? 'Modifica Utente' : 'Nuovo Utente')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Utenti</a></li>
    <li class="breadcrumb-item active">{{ $user ? 'Modifica' : 'Nuovo' }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-{{ $user ? 'pencil' : 'person-plus' }} me-2"></i>
                    {{ $user ? 'Modifica Utente: ' . $user->name : 'Nuovo Utente' }}
                </h5>
            </div>
            <div class="card-body p-4">

                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST"
                    action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
                    @csrf
                    @if($user)
                        @method('PUT')
                    @endif

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nome completo *</label>
                        <input type="text" id="name" name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $user?->name) }}"
                            placeholder="Nome e cognome"
                            required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Nome utente *</label>
                        <input type="text" id="username" name="username"
                            class="form-control @error('username') is-invalid @enderror"
                            value="{{ old('username', $user?->username) }}"
                            placeholder="username"
                            autocomplete="off"
                            required>
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">
                            Password {{ $user ? '<small class="text-muted">(lascia vuoto per non cambiare)</small>' : '*' }}
                        </label>
                        <input type="password" id="password" name="password"
                            class="form-control @error('password') is-invalid @enderror"
                            placeholder="{{ $user ? 'Nuova password (opzionale)' : 'Password' }}"
                            autocomplete="new-password"
                            {{ $user ? '' : 'required' }}>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label fw-semibold">Ruolo *</label>
                        <select id="role" name="role"
                            class="form-select @error('role') is-invalid @enderror"
                            required>
                            <option value="">— Seleziona ruolo —</option>
                            <option value="operator" {{ old('role', $user?->role) === 'operator' ? 'selected' : '' }}>
                                Operatore
                            </option>
                            <option value="backoffice" {{ old('role', $user?->role) === 'backoffice' ? 'selected' : '' }}>
                                Backoffice
                            </option>
                            <option value="viewer" {{ old('role', $user?->role) === 'viewer' ? 'selected' : '' }}>
                                Visualizzatore
                            </option>
                            <option value="admin" {{ old('role', $user?->role) === 'admin' ? 'selected' : '' }}>
                                Amministratore
                            </option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="active" name="active"
                                value="1"
                                {{ old('active', $user ? ($user->active ? '1' : '0') : '1') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="active">
                                Account attivo
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-floppy me-1"></i>
                            {{ $user ? 'Aggiorna Utente' : 'Crea Utente' }}
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                            Annulla
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
