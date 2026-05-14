<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\InventoryRecord;
use App\Models\Warehouse;
use App\Services\ArticleLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    public function location()
    {
        $warehouses = Warehouse::where('active', true)
            ->with(['activeAreas' => fn($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('scan.location', compact('warehouses'));
    }

    public function selectLocation(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'area_id'      => 'required|exists:areas,id',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $area      = Area::where('id', $request->area_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->firstOrFail();

        session([
            'warehouse_id'   => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'area_id'        => $area->id,
            'area_name'      => $area->name,
        ]);

        return redirect()->route('scan');
    }

    public function scanner()
    {
        if (! session('warehouse_id') || ! session('area_id')) {
            return redirect()->route('location');
        }

        $warehouseName = session('warehouse_name');
        $areaName      = session('area_name');

        return view('scan.scanner', compact('warehouseName', 'areaName'));
    }

    public function lookupArticle(Request $request)
    {
        $request->validate([
            'article_code' => 'required|string|max:255',
            'lot'          => 'nullable|string|max:255',
            'scan_type'    => 'required|in:qr,barcode',
        ]);

        $articleCode = trim($request->article_code);
        $lot         = trim($request->lot ?? '');

        $service = app(ArticleLookupService::class);
        $result  = $service->lookup($articleCode, $lot);

        $result['lot_match'] = $result['found'] && ($result['lot'] === $lot);

        return response()->json($result);
    }

    public function showArticle(Request $request)
    {
        if (! session('warehouse_id') || ! session('area_id')) {
            return redirect()->route('location');
        }

        $areaId = session('area_id');
        $area   = Area::findOrFail($areaId);

        $data = [
            'article_code' => $request->query('article_code', ''),
            'description'  => $request->query('description', ''),
            'um'           => $request->query('um', ''),
            'lot'          => $request->query('lot', ''),
            'db_source'    => $request->query('db_source', 'not_found'),
            'lot_match'    => filter_var($request->query('lot_match', 'false'), FILTER_VALIDATE_BOOLEAN),
            'area'         => $area,
            'warehouseName' => session('warehouse_name'),
            'areaName'      => session('area_name'),
        ];

        return view('scan.article', $data);
    }

    public function saveRecord(Request $request)
    {
        $request->validate([
            'article_code' => 'required|string|max:255',
            'description'  => 'required|string|max:500',
            'um'           => 'nullable|string|max:50',
            'lot'          => 'nullable|string|max:255',
            'quantity'     => 'required|numeric|min:0',
            'db_source'    => 'nullable|string|max:50',
            'sample_count' => 'nullable|integer|min:1',
            'sample_weight'=> 'nullable|numeric|min:0',
            'total_weight' => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if (! session('warehouse_id') || ! session('area_id')) {
            return redirect()->route('location');
        }

        InventoryRecord::create([
            'user_id'      => Auth::id(),
            'warehouse_id' => session('warehouse_id'),
            'area_id'      => session('area_id'),
            'article_code' => $request->article_code,
            'description'  => $request->description,
            'um'           => $request->um,
            'lot'          => $request->lot,
            'quantity'     => $request->quantity,
            'db_source'    => $request->db_source ?? 'not_found',
            'sample_count' => $request->sample_count,
            'sample_weight'=> $request->sample_weight,
            'total_weight' => $request->total_weight,
            'notes'        => $request->notes,
        ]);

        return redirect()->route('scan')->with('success', 'Articolo salvato con successo.');
    }
}
