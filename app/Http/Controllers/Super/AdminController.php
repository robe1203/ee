<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AdminController extends Controller

{

private function targetRole(Request $request): string
{
    $role = $request->user()->role;

    if ($role === 'superadmin') return 'admin';
    if ($role === 'admin') return 'alumno';

    abort(403);
}

private function baseRouteName(Request $request): string
{
    return $request->user()->role === 'superadmin' ? 'super.users.' : 'admin.users.';
}
    public function index()
    {
        $admins = User::where('role', 'admin')
            ->orderByDesc('id')
            ->get(['id','name','email','role','created_at']);

        return Inertia::render('Super/Admins/Index', [
            'admins' => $admins,
        ]);
    }

    public function create()
    {
        return Inertia::render('Super/Admins/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'admin',
        ]);

        return redirect()->route('super.admins.index')->with('success', 'Administrador creado.');
    }

    public function edit(User $user)
    {
        abort_unless($user->role === 'admin', 404);

        return Inertia::render('Super/Admins/Edit', [
            'admin' => $user->only('id','name','email'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->role === 'admin', 404);

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email,'.$user->id],
            'password' => ['nullable','string','min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', 'Administrador actualizado.');
    }

    public function destroy(User $user)
    {
        abort_unless($user->role === 'admin', 404);

        $user->delete();

        return back()->with('success', 'Administrador eliminado.');
    }

    
}
