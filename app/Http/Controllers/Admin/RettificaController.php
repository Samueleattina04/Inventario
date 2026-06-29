<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InventoryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RettificaController extends Controller
{
    public function index(Request $request)
    {
        $record   = null;
        $notFound = false;

        if ($request->filled('id')) {
            $record = InventoryRecord::with(['user', 'warehouse', 'area'])->find($request->id);
            if (! $record) {
                $notFound = true;
            }
        }

        return view('admin.rettifica.index', compact('record', 'notFound'));
    }

    public function update(Request $request, InventoryRecord $record)
    {
        $request->validate([
            'article_code' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'um'           => 'nullable|string|max:50',
            'lot'          => 'nullable|string|max:255',
            'quantity'     => 'required|numeric|min:0',
        ], [
            'article_code.required' => 'Il codice articolo è obbligatorio.',
            'quantity.required'     => 'La quantità è obbligatoria.',
            'quantity.numeric'      => 'La quantità deve essere un numero.',
        ]);

        $fields    = ['article_code', 'description', 'um', 'lot', 'quantity'];
        $oldValues = $record->only($fields);
        $newValues = $request->only($fields);

        $hasChanges = collect($fields)->contains(
            fn($k) => ($oldValues[$k] ?? null) != ($newValues[$k] ?? null)
        );

        if (! $hasChanges) {
            return redirect()->route('admin.rettifica.index', ['id' => $record->id])
                ->with('info', 'Nessuna modifica rilevata.');
        }

        $record->update(array_merge($newValues, ['rettified' => true]));

        ActivityLog::create([
            'user_id'      => Auth::id(),
            'action'       => 'rettifica',
            'subject_type' => 'InventoryRecord',
            'subject_id'   => $record->id,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'description'  => "Rettifica registrazione #{$record->id} articolo {$record->article_code}",
        ]);

        return redirect()->route('admin.rettifica.index')
            ->with('success', "Rettifica #{$record->id} (articolo {$record->article_code}) salvata.");
    }
}
