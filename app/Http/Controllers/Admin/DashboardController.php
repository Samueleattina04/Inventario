<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        request()->session()->save();

        $todayCount = InventoryRecord::where('hidden', false)->whereDate('created_at', today())->count();
        $totalCount = InventoryRecord::where('hidden', false)->count();

        $activeOperators = User::where('role', 'operator')
            ->where('active', true)
            ->count();

        $byWarehouse = Warehouse::withCount(['inventoryRecords' => fn($q) => $q->where('hidden', false)->whereDate('created_at', today())])
            ->orderByDesc('inventory_records_count')
            ->get();

        $lastRecords = InventoryRecord::with(['user', 'warehouse', 'area'])
            ->where('hidden', false)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $demoMode = Cache::get('demo_mode', false);

        return view('admin.dashboard', compact(
            'todayCount',
            'totalCount',
            'activeOperators',
            'byWarehouse',
            'lastRecords',
            'demoMode'
        ));
    }

    public function toggleDemoMode()
    {
        $current = Cache::get('demo_mode', false);
        if ($current) {
            Cache::forget('demo_mode');
        } else {
            Cache::put('demo_mode', true, now()->addHours(12));
        }
        $status = $current ? 'disattivata' : 'attivata';
        return back()->with('success', "Modalità Demo {$status}.");
    }

    public function stats()
    {
        request()->session()->save();

        $todayCount = InventoryRecord::where('hidden', false)->whereDate('created_at', today())->count();
        $totalCount = InventoryRecord::where('hidden', false)->count();

        $lastRecords = InventoryRecord::with(['user', 'warehouse', 'area'])
            ->where('hidden', false)
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
