<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EsolverDetail;
use App\Models\InventoryRecord;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EsolverDetailController extends Controller
{
    public function index(Request $request)
    {
        ini_set('memory_limit', '512M');

        $count      = EsolverDetail::count();
        $lastUpdate = EsolverDetail::latest('updated_at')->value('updated_at');

        $rows       = collect();
        $filter     = $request->get('filter', 'diff');
        $magFilter  = $request->get('mag', '');

        if ($count > 0) {
            $magMap           = Warehouse::pluck('name', 'code');
            $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();

            // Available mag values for filter dropdown
            $availableMags = EsolverDetail::distinct()->orderBy('mag')->pluck('mag');

            // Default to first mag if none selected
            if ($magFilter === '' && $availableMags->isNotEmpty()) {
                $magFilter = $availableMags->first();
            }

            // Aggregate Esolver per mag + article_code (filtered by mag)
            $esolver = EsolverDetail::selectRaw(
                'mag, article_code, MAX(description) as description, MAX(um) as um, SUM(quantity) as esolver_qty'
            )
                ->where('mag', $magFilter)
                ->groupBy('mag', 'article_code')
                ->get()
                ->keyBy(fn($r) => $r->mag . '||' . $r->article_code);

            // Find warehouse_id for this mag
            $magWarehouseId = array_search($magFilter, $warehouseCodeMap);

            // Aggregate inventory count for this warehouse + article_code
            $countsQuery = InventoryRecord::where('hidden', false)
                ->with('warehouse:id,name,code')
                ->selectRaw('warehouse_id, article_code, MAX(description) as description, SUM(quantity) as count_qty')
                ->groupBy('warehouse_id', 'article_code');

            if ($magWarehouseId !== false) {
                $countsQuery->where('warehouse_id', $magWarehouseId);
            } else {
                $countsQuery->whereRaw('1=0'); // no warehouse match
            }

            $counts = $countsQuery->get()
                ->map(function ($r) use ($warehouseCodeMap) {
                    $r->wh_code = $warehouseCodeMap[$r->warehouse_id] ?? ('W'.$r->warehouse_id);
                    return $r;
                })
                ->keyBy(fn($r) => $r->wh_code . '||' . $r->article_code);

            $allKeys = $esolver->keys()->merge($counts->keys())->unique();

            $rows = $allKeys->map(function ($key) use ($esolver, $counts, $magMap) {
                $e   = $esolver->get($key);
                $c   = $counts->get($key);

                $parts       = explode('||', $key, 2);
                $mag         = $parts[0];
                $articleCode = $parts[1] ?? '';

                $esolverQty    = $e ? (float) $e->esolver_qty : null;
                $countQty      = $c ? (float) $c->count_qty   : null;
                $warehouseName = $c?->warehouse?->name ?? ($magMap[$mag] ?? $mag);

                if ($esolverQty !== null && $countQty === null) {
                    $rettifica = 0;
                } elseif ($countQty !== null) {
                    $rettifica = $countQty;
                } else {
                    $rettifica = 0;
                }

                $isDiff = round((float)$esolverQty, 4) !== round((float)$countQty, 4)
                       || $esolverQty === null || $countQty === null;

                return (object) [
                    'mag'          => $mag,
                    'warehouse'    => $warehouseName,
                    'article_code' => $articleCode,
                    'description'  => $e ? $e->description : ($c ? $c->description : ''),
                    'um'           => $e ? $e->um : '',
                    'esolver_qty'  => $esolverQty,
                    'count_qty'    => $countQty,
                    'rettifica'    => $rettifica,
                    'is_diff'      => $isDiff,
                    'only_count'   => $esolverQty === null,
                    'only_esolver' => $countQty === null,
                ];
            })->sortBy('article_code')->values();

            if ($filter === 'diff') {
                $rows = $rows->filter(fn($r) => $r->is_diff)->values();
            } elseif ($filter === 'only_count') {
                $rows = $rows->filter(fn($r) => $r->only_count)->values();
            } elseif ($filter === 'only_esolver') {
                $rows = $rows->filter(fn($r) => $r->only_esolver)->values();
            }

            $totalRows = $rows->count();
        }

        $totalRows     = $totalRows ?? 0;
        $availableMags = $availableMags ?? collect();
        $magMap        = $magMap ?? collect();

        return view('admin.esolver-detail.index', compact('count', 'lastUpdate', 'rows', 'filter', 'totalRows', 'magFilter', 'availableMags', 'magMap'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ], [
            'file.required' => 'Seleziona un file Excel.',
            'file.mimes'    => 'Il file deve essere in formato Excel (.xlsx o .xls).',
        ]);

        ini_set('memory_limit', '512M');

        $path   = $request->file('file')->getRealPath();
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        array_shift($rows); // skip header

        // Layout: [0]=Mag [1]=Articolo [2]=Descrizione [3]=Lotto_alfa [4]=Lotto_data [5]=Lotto_num [6]=Collocazione [7]=UdM [8]=Giacenza
        $data = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row[1] ?? ''));
            if ($code === '') continue;

            $data[] = [
                'mag'          => trim((string) ($row[0] ?? '')),
                'article_code' => $code,
                'description'  => trim((string) ($row[2] ?? '')),
                'lot'          => trim((string) ($row[3] ?? '')) ?: null,
                'um'           => trim((string) ($row[7] ?? '')),
                'quantity'     => is_numeric($row[8] ?? null) ? (float) $row[8] : 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        EsolverDetail::truncate();

        foreach (array_chunk($data, 500) as $chunk) {
            EsolverDetail::insert($chunk);
        }

        return back()->with('success', 'Importati ' . count($data) . ' righe dal file Esolver APP.');
    }

    public function export()
    {
        ini_set('memory_limit', '512M');

        if (EsolverDetail::count() === 0) {
            return back()->with('error', 'Nessun dato Esolver APP caricato.');
        }

        $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();

        // Aggregate Esolver per mag + article
        $esolver = EsolverDetail::selectRaw('mag, article_code, SUM(quantity) as esolver_qty')
            ->groupBy('mag', 'article_code')
            ->get()
            ->keyBy(fn($r) => $r->mag . '||' . $r->article_code);

        // Aggregate count per warehouse code + article
        $counts = InventoryRecord::where('hidden', false)
            ->selectRaw('warehouse_id, article_code, SUM(quantity) as count_qty')
            ->groupBy('warehouse_id', 'article_code')
            ->get()
            ->map(function ($r) use ($warehouseCodeMap) {
                $r->wh_code = $warehouseCodeMap[$r->warehouse_id] ?? ('W'.$r->warehouse_id);
                return $r;
            })
            ->keyBy(fn($r) => $r->wh_code . '||' . $r->article_code);

        $allKeys = $esolver->keys()->merge($counts->keys())->unique()->sort();

        // Export: one row per article (sum across all mag)
        $exportMap = [];
        foreach ($allKeys as $key) {
            $e   = $esolver->get($key);
            $c   = $counts->get($key);

            $parts       = explode('||', $key, 2);
            $articleCode = $parts[1] ?? $parts[0];

            $esolverQty = $e ? (float) $e->esolver_qty : null;
            $countQty   = $c ? (float) $c->count_qty   : null;

            $rettifica = ($esolverQty !== null && $countQty === null) ? 0 : (float)$countQty;

            if (!isset($exportMap[$articleCode])) {
                $exportMap[$articleCode] = 0;
            }
            $exportMap[$articleCode] += $rettifica;
        }

        ksort($exportMap);

        $lines = ["Articolo;Variante;Area;Data;Numero;Codice;Collocazione;Quantità UdM 1;Quantità UdM 2;Codice a barre;Unità logistica"];

        foreach ($exportMap as $articleCode => $rettifica) {
            $qtyFormatted = (floor($rettifica) == $rettifica)
                ? (int) $rettifica
                : number_format($rettifica, 2, '.', '');
            $lines[] = "{$articleCode};;;;;;;{$qtyFormatted};;;";
        }

        $content  = implode("\r\n", $lines);
        $filename = 'rettifica_' . now()->format('Ymd_His') . '.txt';

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
