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
        request()->session()->put('inventory_index_url', $request->fullUrl());
        request()->session()->save();

        $query = $this->applyFilters(
            InventoryRecord::with(['user', 'warehouse', 'area'])->where('hidden', false)->orderByDesc('created_at'),
            $request
        );

        $records    = $query->paginate(30)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();
        $areas      = Area::orderBy('name')->get();
        $operators  = User::where('role', 'operator')->orderBy('name')->get();
        $ums        = InventoryRecord::whereNotNull('um')->where('um', '!=', '')->distinct()->orderBy('um')->pluck('um');

        return view('admin.inventory.index', compact('records', 'warehouses', 'areas', 'operators', 'ums'));
    }

    public function grouped(Request $request)
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $operators  = User::where('role', 'operator')->orderBy('name')->get();

        // Paginated article summary
        $articles = $this->applyFilters(
                InventoryRecord::query()->where('hidden', false)->orderBy('article_code'),
                $request
            )
            ->selectRaw('article_code, description, um, SUM(quantity) as total_qty, COUNT(*) as record_count')
            ->groupBy('article_code', 'description', 'um')
            ->paginate(25)
            ->withQueryString();

        // Lot details for the articles on this page only
        $codes      = $articles->pluck('article_code');
        $lotDetails = $this->applyFilters(
                InventoryRecord::with(['warehouse', 'area', 'user'])->where('hidden', false)->orderBy('article_code')->orderByDesc('created_at'),
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
        if ($request->filled('sample_count')) {
            $request->merge(['sample_count' => (int) str_replace(['.', ',', ' '], '', $request->sample_count)]);
        }

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'area_id'      => 'nullable|exists:areas,id',
            'article_code' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'um'           => 'nullable|string|max:50',
            'lot'          => 'nullable|string|max:255',
            'expiry_date'  => 'nullable|date',
            'quantity'     => 'required|numeric|min:0',
            'sample_count' => 'nullable|integer|min:1',
            'sample_weight'=> 'nullable|numeric|min:0',
            'total_weight' => 'nullable|numeric|min:0',
            'tare'         => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
            'db_source'    => 'nullable|in:not_found,sqlsrv,access',
        ], [
            'warehouse_id.required' => 'Il magazzino è obbligatorio.',
            'article_code.required' => 'Il codice articolo è obbligatorio.',
            'quantity.required'     => 'La quantità è obbligatoria.',
            'quantity.numeric'      => 'La quantità deve essere un numero.',
            'quantity.min'          => 'La quantità non può essere negativa.',
        ]);

        $fields = ['warehouse_id', 'area_id', 'article_code', 'description', 'um',
                   'lot', 'expiry_date', 'quantity', 'sample_count', 'sample_weight',
                   'total_weight', 'tare', 'notes'];

        // Allow changing db_source only when record was not_found
        if ($record->db_source === 'not_found' && $request->filled('db_source')) {
            $fields[] = 'db_source';
        }

        $record->load('warehouse', 'area');
        $oldValues = $record->only($fields);
        $oldValues['warehouse_name'] = $record->warehouse?->name;
        $oldValues['area_name']      = $record->area?->name;

        $record->update($request->only($fields));
        $record->load('warehouse', 'area');

        $newValues = $record->fresh()->only($fields);
        $newValues['warehouse_name'] = $record->warehouse?->name;
        $newValues['area_name']      = $record->area?->name;

        $hasChanges = array_filter(array_keys($oldValues), fn($k) => ($oldValues[$k] ?? null) != ($newValues[$k] ?? null));

        if ($hasChanges) {
            \App\Models\ActivityLog::create([
                'user_id'      => \Auth::id(),
                'action'       => 'admin_edit',
                'subject_type' => 'InventoryRecord',
                'subject_id'   => $record->id,
                'old_values'   => $oldValues,
                'new_values'   => $newValues,
                'description'  => "Modifica registrazione #{$record->id} articolo {$record->article_code}",
            ]);
        }

        $backUrl = session('inventory_index_url', route('admin.inventory.index'));
        return redirect($backUrl)->with('success', 'Registrazione aggiornata.');
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
        $backUrl = session('inventory_index_url', route('admin.inventory.index'));
        return redirect($backUrl)
            ->with('success', 'Registrazione eliminata.');
    }

    public function hiddenIndex(Request $request)
    {
        request()->session()->save();

        $records    = InventoryRecord::with(['user', 'warehouse', 'area'])
            ->where('hidden', true)
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();
        $areas      = Area::orderBy('name')->get();
        $operators  = User::where('role', 'operator')->orderBy('name')->get();

        return view('admin.inventory.hidden', compact('records', 'warehouses', 'areas', 'operators'));
    }

    public function hide(InventoryRecord $record)
    {
        $record->update(['hidden' => true]);

        \App\Models\ActivityLog::create([
            'user_id'      => \Auth::id(),
            'action'       => 'admin_hide',
            'subject_type' => 'InventoryRecord',
            'subject_id'   => $record->id,
            'description'  => "Nascosta registrazione #{$record->id} articolo {$record->article_code}",
        ]);

        return back()->with('success', 'Registrazione nascosta.');
    }

    public function unhide(InventoryRecord $record)
    {
        $record->update(['hidden' => false]);

        \App\Models\ActivityLog::create([
            'user_id'      => \Auth::id(),
            'action'       => 'admin_unhide',
            'subject_type' => 'InventoryRecord',
            'subject_id'   => $record->id,
            'description'  => "Ripristinata registrazione #{$record->id} articolo {$record->article_code}",
        ]);

        return back()->with('success', 'Registrazione ripristinata.');
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $filters  = $request->only(['date_from', 'time_from', 'date_to', 'time_to', 'warehouse_id', 'area_id', 'user_id', 'source', 'search', 'um']);
        $filename = 'inventario_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new InventoryExport($filters), $filename);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('date_from')) {
            $from = $request->date_from . ' ' . ($request->filled('time_from') ? $request->time_from . ':00' : '00:00:00');
            $query->where('created_at', '>=', $from);
        }
        if ($request->filled('date_to')) {
            $to = $request->date_to . ' ' . ($request->filled('time_to') ? $request->time_to . ':59' : '23:59:59');
            $query->where('created_at', '<=', $to);
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
        if ($request->filled('um')) {
            $query->where('um', $request->um);
        }
        if ($request->filled('has_calc')) {
            if ($request->has_calc === '1') {
                $query->whereNotNull('sample_count');
            } else {
                $query->whereNull('sample_count');
            }
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
