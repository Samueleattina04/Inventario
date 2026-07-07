<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,operator,backoffice',
            'active'   => 'boolean',
        ]);

        User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'active'   => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Utente creato con successo.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role'     => 'required|in:admin,operator,backoffice',
            'active'   => 'boolean',
        ]);

        $data = [
            'name'     => $request->name,
            'username' => $request->username,
            'role'     => $request->role,
            'active'   => $request->boolean('active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'Utente aggiornato con successo.');
    }

    public function toggleActive(User $user)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Non puoi disattivare il tuo account.');
        }

        $user->update(['active' => ! $user->active]);
        $status = $user->active ? 'attivato' : 'disattivato';

        return back()->with('success', "Utente {$user->name} {$status}.");
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Non puoi eliminare il tuo account.');
        }

        try {
            $user->delete();
        } catch (QueryException $e) {
            return redirect()->route('admin.users.index')
                ->with('error', "Impossibile eliminare l'utente: ha registrazioni o log associati. Disattivalo invece di eliminarlo.");
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Utente eliminato con successo.');
    }
}
