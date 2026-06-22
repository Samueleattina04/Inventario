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
            'area_id'      => 'nullable|exists:areas,id',
        ]);

        $warehouse = Warehouse::with('activeAreas')->findOrFail($request->warehouse_id);

        $hasAreas = $warehouse->activeAreas->isNotEmpty();

        if ($hasAreas && ! $request->filled('area_id')) {
            return back()->withErrors(['area_id' => 'Seleziona un\'area.'])->withInput();
        }

        $area = null;
        if ($request->filled('area_id')) {
            $area = Area::where('id', $request->area_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->firstOrFail();
        }

        session([
            'warehouse_id'   => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'area_id'        => $area?->id,
            'area_name'      => $area?->name ?? '',
        ]);

        return redirect()->route('scan');
    }

    public function scanner()
    {
        if (! session('warehouse_id')) {
            return redirect()->route('location');
        }

        $warehouseName = session('warehouse_name');
        $areaName      = session('area_name');

        return view('scan.scanner', compact('warehouseName', 'areaName'));
    }

    public function lookupArticle(Request $request)
    {
        $request->validate([
            'lot'       => 'required|string|max:255',
            'scan_type' => 'required|in:qr,barcode,unified',
        ]);

        $result = app(ArticleLookupService::class)->lookup(trim($request->lot));

        return response()->json($result);
    }

    public function searchArticles(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $results = app(ArticleLookupService::class)->searchArticles(trim($request->q));

        return response()->json($results);
    }

    public function showArticle(Request $request)
    {
        if (! session('warehouse_id')) {
            return redirect()->route('location');
        }

        $areaId = session('area_id');
        $area   = $areaId ? Area::findOrFail($areaId) : null;

        $data = [
            'article_code' => $request->query('article_code', ''),
            'description'  => $request->query('description', ''),
            'um'           => $request->query('um', ''),
            'lot'          => $request->query('lot', ''),
            'expiry_date'  => $request->query('expiry_date', ''),
            'db_source'    => $request->query('db_source', 'not_found'),
            'lot_match'    => filter_var($request->query('lot_match', 'false'), FILTER_VALIDATE_BOOLEAN),
            'area'         => $area,
            'warehouseName' => session('warehouse_name'),
            'areaName'      => session('area_name'),
        ];

        return view('scan.article', $data);
    }

    private function normalizeNumericFields(Request $request): void
    {
        if ($request->filled('sample_count')) {
            $request->merge(['sample_count' => (int) str_replace(['.', ',', ' '], '', $request->sample_count)]);
        }
        foreach (['sample_weight', 'total_weight', 'quantity'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => self::parseItalianDecimal((string) $request->$field)]);
            }
        }
    }

    private static function parseItalianDecimal(string $val): string
    {
        $val = trim($val);
        if (str_contains($val, '.') && str_contains($val, ',')) {
            return str_replace(['.', ','], ['', '.'], $val);
        }
        if (str_contains($val, ',')) {
            return str_replace(',', '.', $val);
        }
        if (str_contains($val, '.')) {
            $parts    = explode('.', $val);
            if (count($parts) > 2) return implode('', $parts);
            $intPart  = $parts[0];
            $fracPart = $parts[1] ?? '';
            if ($intPart !== '0' && strlen($fracPart) === 3) {
                return $intPart . $fracPart;
            }
        }
        return $val;
    }

    public function saveRecord(Request $request)
    {
        $this->normalizeNumericFields($request);

        $request->validate([
            'article_code' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'um'           => 'nullable|string|max:50',
            'lot'          => 'nullable|string|max:255',
            'expiry_date'  => 'nullable|date',
            'quantity'     => 'required|numeric|min:0',
            'db_source'    => 'nullable|string|max:50',
            'sample_count' => 'nullable|integer|min:1',
            'sample_weight'=> 'nullable|numeric|min:0',
            'total_weight' => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
        ], [
            'article_code.required' => 'Il codice articolo è obbligatorio.',
            'quantity.required'     => 'La quantità è obbligatoria.',
            'quantity.numeric'      => 'La quantità deve essere un numero.',
            'quantity.min'          => 'La quantità non può essere negativa.',
        ]);

        if (! session('warehouse_id')) {
            return redirect()->route('location');
        }

        $record = InventoryRecord::create([
            'user_id'      => Auth::id(),
            'warehouse_id' => session('warehouse_id'),
            'area_id'      => session('area_id'),
            'article_code' => $request->article_code,
            'description'  => $request->description,
            'um'           => $request->um,
            'lot'          => $request->lot,
            'expiry_date'  => $request->expiry_date ?: null,
            'quantity'     => $request->quantity,
            'db_source'    => $request->db_source ?? 'not_found',
            'sample_count' => $request->sample_count,
            'sample_weight'=> $request->sample_weight,
            'total_weight' => $request->total_weight,
            'notes'        => $request->notes,
        ]);

        \App\Models\ActivityLog::create([
            'user_id'      => \Auth::id(),
            'action'       => 'scan_save',
            'subject_type' => 'InventoryRecord',
            'subject_id'   => $record->id,
            'description'  => "Scansione articolo {$record->article_code} lotto {$record->lot} qty {$record->quantity}",
        ]);

        return redirect()->route('scan')->with('success', 'Articolo salvato con successo.');
    }
}
