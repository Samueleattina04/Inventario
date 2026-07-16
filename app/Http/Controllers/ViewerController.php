<?php

namespace App\Http\Controllers;

use App\Models\EsolverReference;
use App\Models\InventoryRecord;
use Illuminate\Http\Request;

class ViewerController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryRecord::with(['warehouse', 'area', 'user'])
            ->where('hidden', false);

        $search  = $request->filled('search') ? trim($request->search) : null;
        $summary = null;
        $details = collect();
        $esolver = null;

        if ($search) {
            $summary = InventoryRecord::where('hidden', false)
                ->where('article_code', $search)
                ->selectRaw('article_code, MAX(description) as description, MAX(um) as um, SUM(quantity) as total_qty, COUNT(*) as record_count')
                ->groupBy('article_code')
                ->first();

            $esolver = EsolverReference::where('article_code', $search)->first();

            $details = InventoryRecord::with(['warehouse', 'area', 'user'])
                ->where('hidden', false)
                ->where('article_code', $search)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('viewer.index', compact('summary', 'details', 'search', 'esolver'));
    }
}
