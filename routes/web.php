<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\Admin\ActivityLogController;
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
    Route::post('/api/article-lookup',   [ScanController::class, 'lookupArticle'])->name('api.article-lookup');
    Route::get('/api/articles/search',   [ScanController::class, 'searchArticles'])->name('api.articles.search');
});

// Admin + Backoffice routes (read/edit access)
Route::middleware(['auth', 'admin_or_backoffice'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',   [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/stats',   [DashboardController::class, 'stats'])->name('api.stats');

    Route::get('/inventory',                        [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/grouped',               [InventoryController::class, 'grouped'])->name('inventory.grouped');
    Route::get('/inventory/export',                [InventoryController::class, 'export'])->name('inventory.export');
    Route::get('/inventory/hidden',                [InventoryController::class, 'hiddenIndex'])->name('inventory.hidden');
    Route::post('/inventory/{record}/hide',        [InventoryController::class, 'hide'])->name('inventory.hide');
    Route::post('/inventory/{record}/unhide',      [InventoryController::class, 'unhide'])->name('inventory.unhide');
    Route::patch('/inventory/{record}/quantity',   [InventoryController::class, 'updateQuantity'])->name('inventory.update-quantity');
    Route::get('/inventory/{record}',              [InventoryController::class, 'show'])->name('inventory.show');
    Route::get('/inventory/{record}/edit',         [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{record}',              [InventoryController::class, 'update'])->name('inventory.update');
});

// Admin-only routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('warehouses', WarehouseController::class);

    Route::post('warehouses/{warehouse}/areas',           [WarehouseController::class, 'storeArea'])->name('warehouses.areas.store');
    Route::put('warehouses/{warehouse}/areas/{area}',    [WarehouseController::class, 'updateArea'])->name('warehouses.areas.update');
    Route::delete('warehouses/{warehouse}/areas/{area}', [WarehouseController::class, 'destroyArea'])->name('warehouses.areas.destroy');

    Route::delete('/inventory/{record}',   [InventoryController::class, 'destroy'])->name('inventory.destroy');

    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity_log.index');
});
