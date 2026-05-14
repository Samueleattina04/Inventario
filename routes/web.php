<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\InventoryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Auth
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Operator routes
Route::middleware('auth')->group(function () {
    Route::get('/location',  [ScanController::class, 'location'])->name('location');
    Route::post('/location', [ScanController::class, 'selectLocation']);
    Route::get('/scan',      [ScanController::class, 'scanner'])->name('scan');
    Route::get('/article',   [ScanController::class, 'showArticle'])->name('article');
    Route::post('/inventory', [ScanController::class, 'saveRecord'])->name('inventory.save');
    Route::post('/api/article-lookup', [ScanController::class, 'lookupArticle'])->name('api.article-lookup');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',   [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/stats',   [DashboardController::class, 'stats'])->name('api.stats');

    Route::resource('users', UserController::class);
    Route::resource('warehouses', WarehouseController::class);

    Route::post('warehouses/{warehouse}/areas',           [WarehouseController::class, 'storeArea'])->name('warehouses.areas.store');
    Route::put('warehouses/{warehouse}/areas/{area}',    [WarehouseController::class, 'updateArea'])->name('warehouses.areas.update');
    Route::delete('warehouses/{warehouse}/areas/{area}', [WarehouseController::class, 'destroyArea'])->name('warehouses.areas.destroy');

    Route::get('/inventory',        [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
});
