<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
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
    /** Cascading category filter (multi-select per level): query param => article_categories column, top to bottom */
    private const CATEGORY_LEVELS = [
        'settore'       => 'settore_code',
        'linea'         => 'linea_code',
        'macrofamiglia' => 'macrofamiglia_code',
        'famiglia'      => 'famiglia_code',
    ];

    private function buildRows(string $magFilter, array $warehouseCodeMap, $magMap): \Illuminate\Support\Collection
    {
        $allMags = ($magFilter === 'all');

        // --- Esolver: aggregate by mag+article (lot codes differ from inventory, can't match) ---
        $esolverQuery = EsolverDetail::selectRaw(
            'mag, article_code, MAX(description) as description, MAX(um) as um, SUM(quantity) as esolver_qty'
        )->groupBy('mag', 'article_code');
        if (!$allMags) {
            $esolverQuery->where('mag', $magFilter);
        }
        $esolverAgg = $esolverQuery->get()
            ->keyBy(fn($r) => $r->mag . '||' . $r->article_code);

        // --- Esolver per lot: used only for "Solo Esolver" rows ---
        $esolverLotsQuery = EsolverDetail::selectRaw(
            'mag, article_code, lot, SUM(quantity) as esolver_lot_qty'
        )->groupBy('mag', 'article_code', 'lot');
        if (!$allMags) {
            $esolverLotsQuery->where('mag', $magFilter);
        }
        $esolverLots = $esolverLotsQuery->get()
            ->groupBy(fn($r) => $r->mag . '||' . $r->article_code);

        // --- Counts: keep per lot for visibility ---
        $countsQuery = InventoryRecord::where('hidden', false)
            ->selectRaw('warehouse_id, article_code, lot, MAX(description) as description, SUM(quantity) as count_qty, MAX(db_source) as db_source')
            ->groupBy('warehouse_id', 'article_code', 'lot');

        if (!$allMags) {
            $magWarehouseId = array_search($magFilter, $warehouseCodeMap);
            if ($magWarehouseId !== false) {
                $countsQuery->where('warehouse_id', $magWarehouseId);
            } else {
                $countsQuery->whereRaw('1=0');
            }
        }

        $countLots = $countsQuery->get()
            ->map(function ($r) use ($warehouseCodeMap) {
                $r->wh_code = $warehouseCodeMap[$r->warehouse_id] ?? ('W'.$r->warehouse_id);
                return $r;
            })
            ->groupBy(fn($r) => $r->wh_code . '||' . $r->article_code);

        // Match at article+mag level
        $allArticleKeys = $esolverAgg->keys()->merge($countLots->keys())->unique();

        $rows = collect();

        foreach ($allArticleKeys as $articleKey) {
            $e      = $esolverAgg->get($articleKey);
            $cLots  = $countLots->get($articleKey);   // collection of inventory lot rows
            $eLots  = $esolverLots->get($articleKey); // collection of Esolver lot rows

            [$mag, $articleCode] = array_pad(explode('||', $articleKey, 2), 2, '');
            $warehouseName = $magMap[$mag] ?? $mag;

            $esolverQtyTotal = $e ? (float) $e->esolver_qty : null;
            $countQtyTotal   = $cLots ? (float) $cLots->sum('count_qty') : null;

            $onlyEsolver = $countQtyTotal === null;
            $onlyCount   = $esolverQtyTotal === null;
            $isDiff      = $onlyEsolver || $onlyCount
                        || round($esolverQtyTotal, 4) !== round($countQtyTotal, 4);

            if ($cLots) {
                // One row per inventory lot; esolver_qty = total for this article+mag
                foreach ($cLots as $cRow) {
                    $countQty       = (float) $cRow->count_qty;
                    $foundInEsolver = str_starts_with($cRow->db_source ?? '', 'sqlsrv');

                    // SQL → code in Articolo Esolver only; ACCESS/N/T → code in Articolo OMNI only
                    $esolverArticle = $foundInEsolver ? $cRow->article_code : ($e ? $e->article_code : '');
                    $omniArticle    = $foundInEsolver ? '' : $cRow->article_code;

                    $rows->push((object) [
                        'mag'            => $mag,
                        'warehouse'      => $warehouseName,
                        'article_code'   => $articleCode,
                        'lot'            => $cRow->lot ?? '',
                        'esolver_article'=> $esolverArticle,
                        'omni_article'   => $omniArticle,
                        'description'    => $e ? $e->description : $cRow->description,
                        'um'             => $e ? $e->um : '',
                        'esolver_qty'    => $esolverQtyTotal,
                        'count_qty'      => $countQty,
                        'rettifica'      => $countQty,
                        'is_diff'        => $isDiff,
                        'only_count'     => $onlyCount,
                        'only_esolver'   => false,
                    ]);
                }
            }

            if ($onlyEsolver && $eLots) {
                // Solo Esolver: one row per Esolver lot
                foreach ($eLots as $eLotRow) {
                    $rows->push((object) [
                        'mag'            => $mag,
                        'warehouse'      => $warehouseName,
                        'article_code'   => $articleCode,
                        'lot'            => $eLotRow->lot ?? '',
                        'esolver_article'=> $articleCode,
                        'omni_article'   => '',
                        'description'    => $e ? $e->description : '',
                        'um'             => $e ? $e->um : '',
                        'esolver_qty'    => (float) $eLotRow->esolver_lot_qty,
                        'count_qty'      => null,
                        'rettifica'      => -(float) $eLotRow->esolver_lot_qty,
                        'is_diff'        => true,
                        'only_count'     => false,
                        'only_esolver'   => true,
                    ]);
                }
            }
        }

        return $rows->sortBy(['mag', 'article_code', 'lot'])->values();
    }

    /** Status, article/description search and category filters shared by the page and both exports */
    private function filterRows(\Illuminate\Support\Collection $rows, Request $request, string $filter): \Illuminate\Support\Collection
    {
        if ($filter === 'diff') {
            $rows = $rows->filter(fn($r) => $r->is_diff);
        } elseif ($filter === 'only_count') {
            $rows = $rows->filter(fn($r) => $r->only_count);
        } elseif ($filter === 'only_esolver') {
            $rows = $rows->filter(fn($r) => $r->only_esolver);
        }

        $search = trim($request->get('search', ''));
        if ($search !== '') {
            $needle = strtoupper($search);
            $rows = $rows->filter(fn($r) =>
                str_contains(strtoupper($r->esolver_article), $needle) ||
                str_contains(strtoupper($r->omni_article), $needle)
            );
        }

        $searchDesc = trim($request->get('search_desc', ''));
        if ($searchDesc !== '') {
            $needle = strtoupper($searchDesc);
            $rows = $rows->filter(fn($r) =>
                str_contains(strtoupper($r->description), $needle)
            );
        }

        $categoryCodes = $this->categoryArticleCodes($request);
        if ($categoryCodes !== null) {
            $rows = $rows->filter(fn($r) =>
                isset($categoryCodes[strtoupper($r->article_code)]) ||
                isset($categoryCodes[strtoupper($r->esolver_article)]) ||
                isset($categoryCodes[strtoupper($r->omni_article)])
            );
        }

        return $rows->values();
    }

    /** Distinct category paths, one row per famiglia (~300 rows) */
    private function categoryTree(): \Illuminate\Support\Collection
    {
        return ArticleCategory::query()
            ->whereNotNull('settore_code')
            ->select('settore_code', 'settore', 'linea_code', 'linea', 'macrofamiglia_code', 'macrofamiglia', 'famiglia_code', 'famiglia')
            ->distinct()
            ->orderBy('settore_code')->orderBy('linea_code')->orderBy('macrofamiglia_code')->orderBy('famiglia_code')
            ->get();
    }

    /** Selected codes per category level, e.g. ['settore' => ['10'], 'linea' => [], ...] */
    private function selectedCategories(Request $request): array
    {
        $selected = [];
        foreach (array_keys(self::CATEGORY_LEVELS) as $param) {
            $selected[$param] = collect((array) $request->get($param, []))
                ->map(fn($v) => trim((string) $v))
                ->filter(fn($v) => $v !== '')
                ->unique()
                ->values()
                ->all();
        }
        return $selected;
    }

    /**
     * Article codes (uppercased, as keys) matching the selected categories, or null if nothing is selected.
     *
     * Each level narrows only its own parent: with Settore = {ALIMENTARE, CONSUMO} and one Linea of
     * CONSUMO selected, all of ALIMENTARE is kept and CONSUMO is limited to that Linea.
     * Selecting a lower level implicitly selects its ancestors.
     */
    private function categoryArticleCodes(Request $request): ?array
    {
        $selected = $this->selectedCategories($request);
        if (collect($selected)->flatten()->isEmpty()) {
            return null;
        }

        $tree    = $this->categoryTree();
        $columns = array_values(self::CATEGORY_LEVELS);
        $params  = array_keys(self::CATEGORY_LEVELS);

        // Effective selection per level: explicit codes plus ancestors of deeper selections
        $effective = array_map(fn($codes) => array_fill_keys($codes, true), array_values($selected));
        for ($lvl = count($columns) - 1; $lvl > 0; $lvl--) {
            foreach ($tree as $node) {
                if (isset($effective[$lvl][$node->{$columns[$lvl]}])) {
                    $effective[$lvl - 1][$node->{$columns[$lvl - 1]}] = true;
                }
            }
        }

        // Parents (at level-1) that have at least one child selected at level
        $narrowed = [];
        for ($lvl = 1; $lvl < count($columns); $lvl++) {
            $narrowed[$lvl] = [];
            foreach ($tree as $node) {
                if (isset($effective[$lvl][$node->{$columns[$lvl]}])) {
                    $narrowed[$lvl][$node->{$columns[$lvl - 1]}] = true;
                }
            }
        }

        $famiglie = $tree->filter(function ($node) use ($columns, $effective, $narrowed) {
            if (!empty($effective[0]) && !isset($effective[0][$node->{$columns[0]}])) {
                return false;
            }
            for ($lvl = 1; $lvl < count($columns); $lvl++) {
                $parent = $node->{$columns[$lvl - 1]};
                if (isset($narrowed[$lvl][$parent]) && !isset($effective[$lvl][$node->{$columns[$lvl]}])) {
                    return false;
                }
            }
            return true;
        })->pluck('famiglia_code')->filter()->unique()->values();

        return ArticleCategory::whereIn('famiglia_code', $famiglie)
            ->pluck('article_code')
            ->mapWithKeys(fn($code) => [strtoupper($code) => true])
            ->all();
    }

    public function index(Request $request)
    {
        ini_set('memory_limit', '512M');

        $count      = EsolverDetail::count();
        $lastUpdate = EsolverDetail::latest('updated_at')->value('updated_at');

        $rows      = collect();
        $filter     = $request->get('filter', 'diff');
        $magFilter  = $request->get('mag', '');
        $search     = trim($request->get('search', ''));
        $searchDesc = trim($request->get('search_desc', ''));
        $totalRows  = 0;

        if ($count > 0) {
            $magMap           = Warehouse::pluck('name', 'code');
            $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();
            $availableMags    = EsolverDetail::distinct()->orderBy('mag')->pluck('mag');

            if ($magFilter === '' && $availableMags->isNotEmpty()) {
                $magFilter = $availableMags->first();
            }

            $rows = $this->filterRows($this->buildRows($magFilter, $warehouseCodeMap, $magMap), $request, $filter);

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

        // Category options for the cascading multi-select: per level, [code, name, parent code]
        $categoryCount   = ArticleCategory::count();
        $categoryTree    = $this->categoryTree();
        $categoryOptions = [];
        $prevColumn      = null;
        foreach (self::CATEGORY_LEVELS as $param => $column) {
            $nameColumn = substr($column, 0, -strlen('_code'));
            $categoryOptions[$param] = $categoryTree
                ->unique($column)
                ->filter(fn($n) => $n->$column !== null)
                ->map(fn($n) => ['code' => $n->$column, 'name' => $n->$nameColumn, 'parent' => $prevColumn ? $n->$prevColumn : null])
                ->values()
                ->all();
            $prevColumn = $column;
        }
        $categoryFilter = $this->selectedCategories($request);

        // Current filters, merged into every link so changing one filter keeps the others
        $query = array_merge(
            ['mag' => $magFilter, 'filter' => $filter, 'search' => $search, 'search_desc' => $searchDesc],
            array_filter($categoryFilter)
        );

        return view('admin.esolver-detail.index',
            compact('count', 'lastUpdate', 'rows', 'filter', 'totalRows', 'magFilter', 'availableMags', 'magMap', 'search', 'searchDesc',
                    'categoryCount', 'categoryOptions', 'categoryFilter', 'query'));
    }

    /** Import "Categorie articoli" Excel: Codice, Descrizione, Settore (cod, des), Linea, Macrofamiglia, Famiglia, Microfamiglia, Classe inv */
    public function importCategories(Request $request)
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
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        array_shift($rows);

        // [0]=Codice [1]=Descrizione [2-3]=Settore [4-5]=Linea [6-7]=Macrofamiglia [8-9]=Famiglia [10]=Microfamiglia [11]=Classe inv
        $cell = function (array $row, int $i): ?string {
            $v = trim(str_replace("\u{00A0}", ' ', (string) ($row[$i] ?? '')));
            return ($v === '' || $v === '0') ? null : $v;
        };

        $data = [];
        foreach ($rows as $row) {
            $code = $cell($row, 0);
            if ($code === null) continue;

            $data[$code] = [
                'article_code'       => $code,
                'description'        => $cell($row, 1),
                'settore_code'       => $cell($row, 2),
                'settore'            => $cell($row, 3),
                'linea_code'         => $cell($row, 4),
                'linea'              => $cell($row, 5),
                'macrofamiglia_code' => $cell($row, 6),
                'macrofamiglia'      => $cell($row, 7),
                'famiglia_code'      => $cell($row, 8),
                'famiglia'           => $cell($row, 9),
                'classe_inv'         => $cell($row, 11),
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        ArticleCategory::truncate();
        foreach (array_chunk(array_values($data), 500) as $chunk) {
            ArticleCategory::insert($chunk);
        }

        return back()->with('success', 'Importate ' . count($data) . ' categorie articoli.');
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

        $header = array_shift($rows);

        // Auto-detect layout by header:
        // Format A (with SOMMARE): Mag, SOMMARE, Articolo, Descrizione, Lotto_alfa, ..., UdM[8], Giacenza[9]
        // Format B (no SOMMARE):   Mag, Articolo, Descrizione, Lotto_alfa, ..., UdM[7], Giacenza[8]
        $headerNorm = array_map(fn($h) => strtolower(trim((string) $h)), $header ?? []);
        $hasSommare = in_array('sommare', $headerNorm);

        if ($hasSommare) {
            // [0]=Mag [1]=SOMMARE [2]=Articolo [3]=Descrizione [4]=Lotto_alfa [8]=UdM [9]=Giacenza
            $colArticle = 2; $colDesc = 3; $colLot = 4; $colUm = 8; $colQty = 9;
        } else {
            // [0]=Mag [1]=Articolo [2]=Descrizione [3]=Lotto_alfa [7]=UdM [8]=Giacenza
            $colArticle = 1; $colDesc = 2; $colLot = 3; $colUm = 7; $colQty = 8;
        }

        $data = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row[$colArticle] ?? ''));
            if ($code === '') continue;

            $data[] = [
                'mag'          => trim((string) ($row[0] ?? '')),
                'article_code' => $code,
                'description'  => trim((string) ($row[$colDesc] ?? '')),
                'lot'          => trim((string) ($row[$colLot] ?? '')) ?: null,
                'um'           => trim((string) ($row[$colUm] ?? '')),
                'quantity'     => is_numeric($row[$colQty] ?? null) ? (float) $row[$colQty] : 0,
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
        $filter           = $request->get('filter', 'all');
        $allMags          = ($magFilter === 'all');
        $warehouseCodeMap = Warehouse::pluck('code', 'id')->toArray();
        $magMap           = Warehouse::pluck('name', 'code');

        $allRows = $this->filterRows($this->buildRows($magFilter, $warehouseCodeMap, $magMap), $request, $filter);

        // Esolver lots needed to emit per-lot correction rows for "Differenza" articles
        $esolverLotsQuery = EsolverDetail::selectRaw('mag, article_code, lot, SUM(quantity) as esolver_lot_qty')
            ->groupBy('mag', 'article_code', 'lot');
        if (!$allMags) {
            $esolverLotsQuery->where('mag', $magFilter);
        }
        $esolverLots = $esolverLotsQuery->get()
            ->groupBy(fn($r) => $r->mag . '||' . $r->article_code);

        $date = now()->format('d/m/Y');

        $headerLine = "TIPO RECORD;TES: TIPO DOCUMENTO;TES: REGISTRAZIONE: DATA;TES: REGISTRAZIONE: NUMERO              (obbligatorio);RIG: TIPO RIGA                          (obbligatorio);RIG: CODICE OPERAZIONE DI MAGAZZINO;RIG: CODICE ARTICOLO;RIG: QUANTITA' PRINCIPALE;RIG: QUANTITA' ESPRESSA NELLA UM SECONDARIA;RIG: CODICE MAGAZZINO PRINCIPALE;RIG/LTC/CDB: RIFERIMENTO LOTTO: CODICE ALFANUMERICO";
        $tesLine    = "TES;703;{$date};9999;;;;;;;";

        $lines = [$headerLine, $tesLine];
        $esolverCorrectionEmitted = [];

        $fmt = fn(float $qty): string => ($s = rtrim(rtrim(number_format($qty, 4, ',', ''), '0'), ',')) === '' ? '0' : $s;

        foreach ($allRows as $row) {
            $articleCode = $row->esolver_article ?: $row->omni_article;
            if ($articleCode === '') continue;

            $mag = $row->mag;
            $lot = $row->lot ?? '';

            if ($row->only_esolver) {
                // Negative: zero out this Esolver lot
                $qty = (float) $row->esolver_qty;
                if (round($qty, 4) == 0) continue;
                $lines[] = "RIG;703;{$date};9999;10;101;{$articleCode};{$fmt($qty)};;{$mag};{$lot}";

            } elseif ($row->only_count) {
                // Positive: add this counted lot
                $qty = (float) $row->count_qty;
                if (round($qty, 4) == 0) continue;
                $lines[] = "RIG;703;{$date};9999;10;100;{$articleCode};{$fmt($qty)};;{$mag};{$lot}";

            } else {
                // Article found in both Esolver and Count (Differenza or Quadra)
                if (!$row->is_diff) continue; // skip Quadra

                $articleKey = $mag . '||' . $row->article_code;

                // Emit Esolver correction rows once per article (negative: zero out each Esolver lot)
                if (!isset($esolverCorrectionEmitted[$articleKey])) {
                    $esolverCorrectionEmitted[$articleKey] = true;
                    foreach ($esolverLots->get($articleKey, collect()) as $eLotRow) {
                        $qty = (float) $eLotRow->esolver_lot_qty;
                        if (round($qty, 4) == 0) continue;
                        $lines[] = "RIG;703;{$date};9999;10;101;{$eLotRow->article_code};{$fmt($qty)};;{$mag};{$eLotRow->lot}";
                    }
                }

                // Positive: add this counted lot
                $qty = (float) $row->count_qty;
                if (round($qty, 4) == 0) continue;
                $lines[] = "RIG;703;{$date};9999;10;100;{$articleCode};{$fmt($qty)};;{$mag};{$lot}";
            }
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

        $rows = $this->filterRows($this->buildRows($magFilter, $warehouseCodeMap, $magMap), $request, $filter);

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
                $stato = 'Solo Conta';
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
