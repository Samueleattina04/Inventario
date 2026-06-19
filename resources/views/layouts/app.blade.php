<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventario Magazzino')</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f4f8; }
        .navbar-brand { font-weight: 700; letter-spacing: 0.5px; }
        .btn { min-height: 44px; }
        .form-control, .form-select { min-height: 44px; font-size: 1rem; }
        .location-badge {
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            padding: 2px 10px;
            font-size: 0.85rem;
        }
        @yield('extra-styles')
    </style>
    @yield('head')
</head>
<body>
<nav class="navbar navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1"><i class="bi bi-box-seam me-2"></i>Inventario</span>
        <div class="d-flex align-items-center gap-2">
            @auth
                @if(session('warehouse_name'))
                    <span class="location-badge text-white d-none d-sm-inline">
                        <i class="bi bi-geo-alt-fill"></i>
                        {{ session('warehouse_name') }} / {{ session('area_name') }}
                    </span>
                @endif
                <span class="text-white-50 small d-none d-sm-inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Esci
                    </button>
                </form>
            @endauth
        </div>
    </div>
</nav>

@auth
    @if(session('warehouse_name'))
        <div class="bg-primary bg-opacity-10 border-bottom border-primary border-opacity-25 py-1 px-3 d-sm-none">
            <small class="text-primary fw-semibold">
                <i class="bi bi-geo-alt-fill"></i>
                {{ session('warehouse_name') }} &rarr; {{ session('area_name') }}
            </small>
        </div>
    @endif
@endauth

<main class="container-fluid py-3">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
