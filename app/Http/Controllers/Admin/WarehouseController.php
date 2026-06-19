<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        request()->session()->save();

        $warehouses = Warehouse::with(['areas' => fn($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();
        return view('admin.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        return view('admin.warehouses.form', ['warehouse' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'code'   => 'required|string|max:50|unique:warehouses,code',
            'active' => 'boolean',
        ]);

        Warehouse::create([
            'name'   => $request->name,
            'code'   => strtoupper($request->code),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Magazzino creato con successo.');
    }

    public function edit(Warehouse $warehouse)
    {
        return view('admin.warehouses.form', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'code'   => 'required|string|max:50|unique:warehouses,code,' . $warehouse->id,
            'active' => 'boolean',
        ]);

        $warehouse->update([
            'name'   => $request->name,
            'code'   => strtoupper($request->code),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Magazzino aggiornato con successo.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();
        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Magazzino eliminato con successo.');
    }

    // ---- Area sub-resource ----

    public function storeArea(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'code'                 => 'required|string|max:50',
            'has_weight_calculator'=> 'boolean',
            'active'               => 'boolean',
        ]);

        $warehouse->areas()->create([
            'name'                  => $request->name,
            'code'                  => strtoupper($request->code),
            'has_weight_calculator' => $request->boolean('has_weight_calculator', false),
            'active'                => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Area creata con successo.');
    }

    public function updateArea(Request $request, Warehouse $warehouse, Area $area)
    {
        abort_if($area->warehouse_id !== $warehouse->id, 404);

        $request->validate([
            'name'                 => 'required|string|max:255',
            'code'                 => 'required|string|max:50',
            'has_weight_calculator'=> 'boolean',
            'active'               => 'boolean',
        ]);

        $area->update([
            'name'                  => $request->name,
            'code'                  => strtoupper($request->code),
            'has_weight_calculator' => $request->boolean('has_weight_calculator', false),
            'active'                => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Area aggiornata con successo.');
    }

    public function destroyArea(Warehouse $warehouse, Area $area)
    {
        abort_if($area->warehouse_id !== $warehouse->id, 404);
        $area->delete();
        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Area eliminata con successo.');
    }
}
