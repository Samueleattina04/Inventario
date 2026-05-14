<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $todayCount = InventoryRecord::whereDate('created_at', today())->count();
        $totalCount = InventoryRecord::count();

        $activeOperators = User::where('role', 'operator')
            ->where('active', true)
            ->count();

        $byWarehouse = Warehouse::withCount(['inventoryRecords' => fn($q) => $q->whereDate('created_at', today())])
            ->orderByDesc('inventory_records_count')
            ->get();

        $lastRecords = InventoryRecord::with(['user', 'warehouse', 'area'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.dashboard', compact(
            'todayCount',
            'totalCount',
            'activeOperators',
            'byWarehouse',
            'lastRecords'
        ));
    }

    public function stats()
    {
        $todayCount = InventoryRecord::whereDate('created_at', today())->count();
        $totalCount = InventoryRecord::count();

        $lastRecords = InventoryRecord::with(['user', 'warehouse', 'area'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'article_code' => $r->article_code,
                'description'  => $r->description,
                'quantity'     => $r->quantity,
                'operator'     => $r->user?->name,
                'warehouse'    => $r->warehouse?->name,
                'area'         => $r->area?->name,
                'created_at'   => $r->created_at?->format('d/m/Y H:i'),
            ]);

        return response()->json([
            'today_count'  => $todayCount,
            'total_count'  => $totalCount,
            'last_records' => $lastRecords,
        ]);
    }
}
