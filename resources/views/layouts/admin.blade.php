<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Inventario</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f4f8; }
        #sidebar {
            min-height: 100vh;
            background: #1a2e4a;
            width: 240px;
            transition: margin-left 0.3s;
        }
        #sidebar .nav-link {
            color: rgba(255,255,255,0.75);
            padding: 0.65rem 1.2rem;
            border-radius: 6px;
            margin: 2px 8px;
            font-size: 0.95rem;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.12);
        }
        #sidebar .nav-link i { width: 22px; }
        #sidebar .sidebar-brand {
            padding: 1.2rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        #sidebar .sidebar-section {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
            padding: 1rem 1.2rem 0.3rem;
        }
        #content { flex: 1; min-width: 0; }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 0.6rem 1rem;
        }
        .btn { min-height: 38px; }
        @media (max-width: 768px) {
            #sidebar { display: none; }
            #sidebar.show { display: block; position: fixed; z-index: 1050; top: 0; left: 0; }
        }
        @yield('extra-styles')
    </style>
    @yield('head')
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-box-seam me-2"></i>Inventario
        </div>
        <div class="sidebar-section">Navigazione</div>
        <ul class="nav flex-column mt-1">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                   href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                   href="{{ route('admin.users.index') }}">
                    <i class="bi bi-people me-2"></i>Utenti
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.warehouses.*') ? 'active' : '' }}"
                   href="{{ route('admin.warehouses.index') }}">
                    <i class="bi bi-building me-2"></i>Magazzini/Aree
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}"
                   href="{{ route('admin.inventory.index') }}">
                    <i class="bi bi-table me-2"></i>Inventario
                </a>
            </li>
        </ul>
        <div class="sidebar-section mt-3">Account</div>
        <ul class="nav flex-column mt-1 pb-3">
            <li class="nav-item">
                <div class="nav-link text-white-50 small">
                    <i class="bi bi-person-circle me-2"></i>{{ auth()->user()->name ?? '' }}
                </div>
            </li>
            <li class="nav-item">
                <form method="POST" action="{{ route('logout') }}" class="px-3 mt-1">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-right me-1"></i>Esci
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <!-- Main content -->
    <div id="content">
        <!-- Top bar -->
        <div class="topbar d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <nav aria-label="breadcrumb" class="mb-0">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
        </div>

        <div class="p-3 p-md-4">
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
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('show');
    });
    document.addEventListener('click', function (e) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if (sidebar && !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
</script>
@yield('scripts')
</body>
</html>
