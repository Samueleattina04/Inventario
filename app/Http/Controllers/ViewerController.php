<?php

namespace App\Http\Controllers;

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

        if ($search) {
            $query->where('article_code', $search);

            $summary = InventoryRecord::where('hidden', false)
                ->where('article_code', $search)
                ->selectRaw('article_code, description, um, SUM(quantity) as total_qty, COUNT(*) as record_count')
                ->groupBy('article_code', 'description', 'um')
                ->first();

            $details = InventoryRecord::with(['warehouse', 'area', 'user'])
                ->where('hidden', false)
                ->where('article_code', $search)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('viewer.index', compact('summary', 'details', 'search'));
    }
}
