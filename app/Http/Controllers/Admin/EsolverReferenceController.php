<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EsolverReference;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EsolverReferenceController extends Controller
{
    public function index()
    {
        $count      = EsolverReference::count();
        $lastUpdate = EsolverReference::latest('updated_at')->value('updated_at');
        return view('admin.esolver.index', compact('count', 'lastUpdate'));
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

        // Skip header row
        array_shift($rows);

        // Aggregate quantities by article code
        // Column layout: [0]=Mag [1]=SOMMARE [2]=Articolo [3]=Descrizione [4-7]=Lotto/Coll [8]=UdM [9]=Giacenza
        $aggregated = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row[2] ?? ''));
            if ($code === '') continue;

            if (!isset($aggregated[$code])) {
                $aggregated[$code] = [
                    'article_code' => $code,
                    'description'  => trim((string) ($row[3] ?? '')),
                    'um'           => trim((string) ($row[8] ?? '')),
                    'quantity'     => 0,
                    'external_qty' => 0,
                ];
            }
            $qty = is_numeric($row[9] ?? null) ? (float) $row[9] : 0;
            $aggregated[$code]['quantity'] += $qty;
            if (strtoupper(trim((string) ($row[1] ?? ''))) === 'OK') {
                $aggregated[$code]['external_qty'] += $qty;
            }
        }

        EsolverReference::truncate();

        foreach (array_chunk(array_values($aggregated), 500) as $chunk) {
            EsolverReference::insert(array_map(fn($r) => array_merge($r, [
                'created_at' => now(),
                'updated_at' => now(),
            ]), $chunk));
        }

        $imported = count($aggregated);
        return back()->with('success', "Importati {$imported} articoli da Esolver (quantità aggregate per lotto).");
    }
}
