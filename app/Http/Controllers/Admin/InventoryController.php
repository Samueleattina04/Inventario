<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InventoryExport;
use App\Http\Controllers\Controller;
use App\Models\Area;
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
        $areas      = Area::orderBy('name')->get();
        $operators  = User::where('role', 'operator')->orderBy('name')->get();

        return view('admin.inventory.index', compact('records', 'warehouses', 'areas', 'operators'));
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

    public function show(InventoryRecord $record)
    {
        $record->load(['user', 'warehouse', 'area']);
        return view('admin.inventory.show', compact('record'));
    }

    public function edit(InventoryRecord $record)
    {
        $record->load(['user', 'warehouse', 'area']);
        $warehouses = Warehouse::orderBy('name')->get()->load('activeAreas');
        return view('admin.inventory.edit', compact('record', 'warehouses'));
    }

    public function update(Request $request, InventoryRecord $record)
    {
        $request->validate([
            'article_code' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'um'           => 'nullable|string|max:50',
            'lot'          => 'nullable|string|max:255',
            'expiry_date'  => 'nullable|date',
            'quantity'     => 'required|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
        ], [
            'article_code.required' => 'Il codice articolo è obbligatorio.',
            'quantity.required'     => 'La quantità è obbligatoria.',
            'quantity.numeric'      => 'La quantità deve essere un numero.',
            'quantity.min'          => 'La quantità non può essere negativa.',
        ]);

        $oldValues = $record->only(['article_code', 'description', 'um', 'lot', 'expiry_date', 'quantity', 'notes']);

        $record->update($request->only([
            'article_code', 'description', 'um', 'lot', 'expiry_date', 'quantity', 'notes',
        ]));

        $newValues = $record->fresh()->only(['article_code', 'description', 'um', 'lot', 'expiry_date', 'quantity', 'notes']);

        \App\Models\ActivityLog::create([
            'user_id'      => \Auth::id(),
            'action'       => 'admin_edit',
            'subject_type' => 'InventoryRecord',
            'subject_id'   => $record->id,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'description'  => "Modifica registrazione #{$record->id} articolo {$record->article_code}",
        ]);

        return redirect()->route('admin.inventory.index')
            ->with('success', 'Registrazione aggiornata.');
    }

    public function destroy(InventoryRecord $record)
    {
        \App\Models\ActivityLog::create([
            'user_id'      => \Auth::id(),
            'action'       => 'admin_delete',
            'subject_type' => 'InventoryRecord',
            'subject_id'   => $record->id,
            'description'  => "Eliminazione registrazione #{$record->id} articolo {$record->article_code} lotto {$record->lot}",
        ]);

        $record->delete();
        return redirect()->route('admin.inventory.index')
            ->with('success', 'Registrazione eliminata.');
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $filters  = $request->only(['date_from', 'date_to', 'warehouse_id', 'area_id', 'user_id', 'source', 'search']);
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
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('article_code', 'like', "%{$search}%")
                  ->orWhere('lot', 'like', "%{$search}%");
            });
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
