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

        $path        = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        // Skip header row
        $header  = array_shift($rows);
        $imported = 0;

        EsolverReference::truncate();

        foreach ($rows as $row) {
            $code = trim((string) ($row[0] ?? ''));
            if ($code === '') continue;

            EsolverReference::create([
                'article_code' => $code,
                'description'  => trim((string) ($row[1] ?? '')),
                'um'           => trim((string) ($row[6] ?? '')),
                'quantity'     => is_numeric($row[7] ?? null) ? (float) $row[7] : 0,
            ]);
            $imported++;
        }

        return back()->with('success', "Importati {$imported} articoli da Esolver.");
    }
}
