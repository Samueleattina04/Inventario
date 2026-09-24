<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EsolverDetail;
use App\Models\InventoryRecord;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EsolverDetailController extends Controller
{
    private function buildRows(string $magFilter, array $warehouseCodeMap, $magMap): \Illuminate\Support\Collection
    {
        $allMags = ($magFilter === 'all');

        // --- Esolver ---
        $esolverQuery = EsolverDetail::selectRaw(
            'mag, article_code, lot, MAX(description) as description, MAX(um) as um, SUM(quantity) as esolver_qty'
        )->groupBy('mag', 'article_code', 'lot');
        if (!$allMags) {
            $esolverQuery->where('mag', $magFilter);
        }
        $esolver = $esolverQuery->get()
            ->keyBy(fn($r) => $r->mag . '||' . $r->article_code . '||' . ($r->lot ?? ''));

        // --- Counts ---
        $countsQuery = InventoryRecord::where('hidden', false)
            ->selectRaw('warehouse_id, article_code, lot, MAX(description) as description, SUM(quantity) as count_qty')
            ->groupBy('warehouse_id', 'article_code', 'lot');

        if (!$allMags) {
            $magWarehouseId = array_search($magFilter, $warehouseCodeMap);
            if ($magWarehouseId !== false) {
                $countsQuery->where('warehouse_id', $magWarehouseId);
            } else {
                $countsQuery->whereRaw('1=0');
            }
        }

        $counts = $countsQuery->get()
            ->map(function ($r) use ($warehouseCodeMap) {
                $r->wh_code = $warehouseCodeMap[$r->warehouse_id] ?? ('W'.$r->warehouse_id);
                return $r;
            })
            ->keyBy(fn($r) => $r->wh_code . '||' . $r->article_code . '||' . ($r->lot ?? ''));

        $allKeys = $esolver->keys()->merge($counts->keys())->unique();

        return $allKeys->map(function ($key) use ($esolver, $counts, $magMap) {
            $e   = $esolver->get($key);
            $c   = $counts->get($key);

            $parts       = explode('||', $key, 3);
            $mag         = $parts[0];
            $articleCode = $parts[1] ?? '';
            $lot         = $parts[2] ?? '';

            $esolverQty    = $e ? (float) $e->esolver_qty : null;
            $countQty      = $c ? (float) $c->count_qty   : null;
            $warehouseName = $magMap[$mag] ?? $mag;

            // Rettifica rules
            if ($esolverQty !== null && $countQty === null) {
                $rettifica = 0;          // In Esolver, not in count
            } elseif ($countQty !== null) {
                $rettifica = $countQty;  // In count (with or without Esolver match)
            } else {
                $rettifica = 0;
            }

            $isDiff = round((float)$esolverQty, 4) !== round((float)$countQty, 4)
                   || $esolverQty === null || $countQty === null;

            // Article codes: Esolver code vs OMNI (inventory) code
            $esolverArticle = $e ? $e->article_code : '';
            $omniArticle    = $c ? $c->article_code : '';

            return (object) [
                'mag'            => $mag,
                'warehouse'      => $warehouseName,
                'article_code'   => $articleCode,
                'lot'            => $lot,
                'esolver_article'=> $esolverArticle,
                'omni_article'   => $omniArticle,
                'description'    => $e ? $e->description : ($c ? $c->description : ''),
                'um'             => $e ? $e->um : '',
                'esolver_qty'    => $esolverQty,
                'count_qty'      => $countQty,
                'rettifica'      => $rettifica,
                'is_diff'        => $isDiff,
                'only_count'     => $esolverQty === null,
                'only_esolver'   => $countQty === null,
            ];
        })->sortBy(['mag', 'article_code', 'lot'])->values();
    }

    public function index(Request $request)
    {
        ini_set('memory_limit', '512M');

        $count      = EsolverDetail::count();
        $lastUpdate = EsolverDetail::latest('updated_at')->value('updated_at');

        $rows      = collect();
        $filter    = $request->get('filter', 'diff');
        $magFilter = $request->get('mag', '');
        $totalRows = 0;

        if ($count > 0) {
            $magMap           = Warehouse::pluck('name', 'code');
            $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();
            $availableMags    = EsolverDetail::distinct()->orderBy('mag')->pluck('mag');

            if ($magFilter === '' && $availableMags->isNotEmpty()) {
                $magFilter = $availableMags->first();
            }

            $rows = $this->buildRows($magFilter, $warehouseCodeMap, $magMap);

            if ($filter === 'diff') {
                $rows = $rows->filter(fn($r) => $r->is_diff)->values();
            } elseif ($filter === 'only_count') {
                $rows = $rows->filter(fn($r) => $r->only_count)->values();
            } elseif ($filter === 'only_esolver') {
                $rows = $rows->filter(fn($r) => $r->only_esolver)->values();
            }

            $totalRows = $rows->count();
            $perPage   = 200;
            $page      = max(1, (int) $request->get('page', 1));
            $rows = new LengthAwarePaginator(
                $rows->forPage($page, $perPage),
                $totalRows,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $availableMags = $availableMags ?? collect();
        $magMap        = $magMap ?? collect();

        return view('admin.esolver-detail.index',
            compact('count', 'lastUpdate', 'rows', 'filter', 'totalRows', 'magFilter', 'availableMags', 'magMap'));
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

        array_shift($rows);

        // Layout: [0]=Mag [1]=Articolo [2]=Descrizione [3]=Lotto_alfa [7]=UdM [8]=Giacenza
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

    /** Download .csv for Esolver import */
    public function export(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        if (EsolverDetail::count() === 0) {
            return back()->with('error', 'Nessun dato Esolver APP caricato.');
        }

        $magFilter        = $request->get('mag', 'all');
        $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();
        $magMap           = Warehouse::pluck('name', 'code');

        $allRows = $this->buildRows($magFilter, $warehouseCodeMap, $magMap);

        $date = now()->format('d/m/Y');

        $headerLine = "TIPO RECORD;TES: TIPO DOCUMENTO;TES: REGISTRAZIONE: DATA;TES: REGISTRAZIONE: NUMERO              (obbligatorio);RIG: TIPO RIGA                          (obbligatorio);RIG: CODICE OPERAZIONE DI MAGAZZINO;RIG: CODICE ARTICOLO;RIG: QUANTITA' PRINCIPALE;RIG: QUANTITA' ESPRESSA NELLA UM SECONDARIA;RIG: CODICE MAGAZZINO PRINCIPALE;RIG/LTC/CDB: RIFERIMENTO LOTTO: CODICE ALFANUMERICO";
        $tesLine    = "TES;703;{$date};9999;;;;;;;";

        $lines = [$headerLine, $tesLine];

        foreach ($allRows as $row) {
            $articleCode = $row->esolver_article ?: $row->omni_article;
            if ($articleCode === '') continue;

            $esolverQty = $row->esolver_qty ?? 0;
            $countQty   = $row->count_qty ?? 0;
            $diff       = $countQty - $esolverQty;

            if (round($diff, 4) == 0) continue;

            $opCode       = $diff > 0 ? 100 : 101;
            $absQty       = abs($diff);
            $qtyFormatted = rtrim(rtrim(number_format($absQty, 4, ',', ''), '0'), ',');
            if ($qtyFormatted === '') $qtyFormatted = '0';

            $mag = $row->mag;
            $lot = $row->lot ?? '';

            $lines[] = "RIG;703;{$date};9999;10;{$opCode};{$articleCode};{$qtyFormatted};;{$mag};{$lot}";
        }

        $content  = implode("\r\n", $lines);
        $filename = 'rettifica_' . now()->format('Ymd_His') . '.csv';

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** Download Excel of comparison table */
    public function exportExcel(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        if (EsolverDetail::count() === 0) {
            return back()->with('error', 'Nessun dato Esolver APP caricato.');
        }

        $magFilter        = $request->get('mag', 'all');
        $filter           = $request->get('filter', 'all');
        $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();
        $magMap           = Warehouse::pluck('name', 'code');

        $rows = $this->buildRows($magFilter, $warehouseCodeMap, $magMap);

        if ($filter === 'diff') {
            $rows = $rows->filter(fn($r) => $r->is_diff)->values();
        } elseif ($filter === 'only_count') {
            $rows = $rows->filter(fn($r) => $r->only_count)->values();
        } elseif ($filter === 'only_esolver') {
            $rows = $rows->filter(fn($r) => $r->only_esolver)->values();
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Confronto Rettifica');

        // Headers
        $headers = ['Mag', 'Magazzino', 'Articolo Esolver', 'Articolo OMNI', 'Lotto', 'Descrizione', 'UM',
                    'Giacenza Esolver', 'Conta Fisica', 'Rettifica Export', 'Stato'];
        $sheet->fromArray($headers, null, 'A1');

        // Header style
        $headerStyle = [
            'font'    => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a2e4a']],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        // Force article code and lot columns (C, D, E) to text format
        $sheet->getStyle('C:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            if ($row->only_esolver) {
                $stato = 'Solo Esolver → 0';
            } elseif ($row->only_count) {
                $stato = 'Solo OMNI';
            } elseif (round((float)$row->esolver_qty, 4) == round((float)$row->count_qty, 4)) {
                $stato = 'Quadra';
            } else {
                $stato = 'Differenza';
            }

            $sheet->fromArray([
                $row->mag,
                $row->warehouse,
                null,
                null,
                null,
                $row->description,
                $row->um,
                $row->esolver_qty,
                $row->count_qty,
                $row->rettifica,
                $stato,
            ], null, "A{$rowNum}");

            // Write text columns explicitly as strings to prevent numeric conversion
            $sheet->setCellValueExplicit("C{$rowNum}", (string) $row->esolver_article, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$rowNum}", (string) $row->omni_article, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$rowNum}", (string) $row->lot, DataType::TYPE_STRING);

            // Highlight differences
            if ($row->is_diff && !$row->only_esolver) {
                $sheet->getStyle("A{$rowNum}:K{$rowNum}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF3CD');
            }

            $rowNum++;
        }

        // Auto-width for key columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Number format for quantity columns
        $sheet->getStyle("H2:J{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');

        $writer   = new Xlsx($spreadsheet);
        $filename = 'confronto_rettifica_' . now()->format('Ymd_His') . '.xlsx';
        $tmpPath  = sys_get_temp_dir() . '/' . $filename;
        $writer->save($tmpPath);

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
