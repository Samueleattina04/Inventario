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

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('article_code', $search);
        }

        $sort = $request->get('sort', 'article_code');
        $dir  = $request->get('dir', 'asc');

        if (!in_array($sort, ['id', 'article_code', 'created_at', 'quantity'])) {
            $sort = 'article_code';
        }
        $dir = $dir === 'desc' ? 'desc' : 'asc';

        $records = $query->orderBy($sort, $dir)->paginate(50)->withQueryString();

        return view('viewer.index', compact('records'));
    }
}
