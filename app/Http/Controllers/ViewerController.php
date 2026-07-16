<?php

namespace App\Http\Controllers;

use App\Models\InventoryRecord;
use Illuminate\Http\Request;

class ViewerController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryRecord::with(['warehouse', 'area'])
            ->where('hidden', false);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('article_code', 'like', "%{$search}%")
                  ->orWhere('lot', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'article_code');
        $dir  = $request->get('dir', 'asc');

        if (!in_array($sort, ['article_code', 'created_at', 'quantity'])) {
            $sort = 'article_code';
        }
        $dir = $dir === 'desc' ? 'desc' : 'asc';

        $records = $query->orderBy($sort, $dir)->paginate(50)->withQueryString();

        return view('viewer.index', compact('records'));
    }
}
