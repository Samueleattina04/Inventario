<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InventoryExport;
use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->applyFilters(
            InventoryRecord::with(['user', 'warehouse', 'area'])->orderByDesc('created_at'),
            $request
        );

        $records    = $query->paginate(30)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();
        $operators  = User::where('role', 'operator')->orderBy('name')->get();

        return view('admin.inventory.index', compact('records', 'warehouses', 'operators'));
    }

    public function grouped(Request $request)
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $operators  = User::where('role', 'operator')->orderBy('name')->get();

        // Paginated article summary
        $articles = $this->applyFilters(
                InventoryRecord::query()->orderBy('article_code'),
                $request
            )
            ->selectRaw('article_code, description, um, SUM(quantity) as total_qty, COUNT(*) as record_count')
            ->groupBy('article_code', 'description', 'um')
            ->paginate(25)
            ->withQueryString();

        // Lot details for the articles on this page only
        $codes      = $articles->pluck('article_code');
        $lotDetails = $this->applyFilters(
                InventoryRecord::with(['warehouse', 'area', 'user'])->orderBy('article_code')->orderByDesc('created_at'),
                $request
            )
            ->whereIn('article_code', $codes)
            ->get()
            ->groupBy('article_code');

        return view('admin.inventory.grouped', compact('articles', 'lotDetails', 'warehouses', 'operators'));
    }

    public function export(Request $request)
    {
        $filters  = $request->only(['date_from', 'date_to', 'warehouse_id', 'user_id', 'source']);
        $filename = 'inventario_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new InventoryExport($filters), $filename);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('source')) {
            match ($request->source) {
                'sqlsrv'    => $query->where(function ($q) {
                                    $q->where('db_source', 'sqlsrv')->orWhere('db_source', 'sqlsrv+access');
                                }),
                'access'    => $query->where('db_source', 'access'),
                'not_found' => $query->where('db_source', 'not_found'),
                default     => null,
            };
        }

        return $query;
    }
}
