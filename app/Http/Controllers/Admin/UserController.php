<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class UserController extends Controller
{
    private function actorRole(): string
    {
        $u = auth()->user();
        return $u && $u->hasRole('superadmin') ? 'superadmin' : 'admin';
    }

    private function manageableRole(): string
    {
        return $this->actorRole() === 'superadmin' ? 'admin' : 'alumno';
    }

    private function allowedRoles(): array
    {
        return [$this->manageableRole()];
    }

    private function quartersList(): array
    {
        return range(1, 11);
    }

    private function quarterColumn(): string
    {
        if (Schema::hasColumn('users', 'cuatrimestre')) {
            return 'cuatrimestre';
        }

        return 'quarter';
    }

    private function ensureCanManageTarget(User $target): void
    {
        if ($target->hasRole('superadmin')) {
            abort(403);
        }

        $allowed = $this->manageableRole();

        if (!$target->hasRole($allowed)) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $roleToManage = $this->manageableRole();
        $qCol = $this->quarterColumn();

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->select('id', 'name', 'email', $qCol, 'is_active')
                ->role($roleToManage)
                ->with('roles:name')
                ->orderByDesc('id')
                ->paginate(10)
                ->through(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'cuatrimestre' => $u->{$qCol},
                    'is_active' => (bool) $u->is_active,
                    'role' => $u->roles->first()?->name ?? null,
                ]),

            'roles' => $this->allowedRoles(),

            'mode' => [
                'actor' => $this->actorRole(),
                'managing' => $roleToManage,
                'quarterColumn' => $qCol,
            ],
        ]);
    }

    public function create()
    {
        $list = $this->quartersList();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->allowedRoles(),
            'quarters' => $list,
            'cuatrimestres' => $list,
            'mode' => [
                'actor' => $this->actorRole(),
                'managing' => $this->manageableRole(),
                'quarterColumn' => $this->quarterColumn(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $qCol = $this->quarterColumn();

        if ($request->has('quarter') && !$request->has($qCol)) {
            $request->merge([$qCol => $request->input('quarter')]);
        }

        if ($request->has('cuatrimestre') && $qCol !== 'cuatrimestre') {
            $request->merge([$qCol => $request->input('cuatrimestre')]);
        }

        $allowed = $this->allowedRoles();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:' . implode(',', $allowed)],
            $qCol => ['nullable', 'integer', 'between:1,11'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['role'] === 'alumno') {
            $request->validate([
                $qCol => ['required', 'integer', 'between:1,11'],
            ]);
        } else {
            $data[$qCol] = null;
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => (bool) $data['is_active'],
        ];

        $payload[$qCol] = $data[$qCol];

        $user = User::create($payload);
        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        $this->ensureCanManageTarget($user);

        $list = $this->quartersList();
        $qCol = $this->quarterColumn();

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cuatrimestre' => $user->{$qCol},
                'is_active' => (bool) $user->is_active,
            ],
            'role' => $user->roles->first()?->name ?? $this->manageableRole(),
            'roles' => $this->allowedRoles(),
            'quarters' => $list,
            'cuatrimestres' => $list,
            'mode' => [
                'actor' => $this->actorRole(),
                'managing' => $this->manageableRole(),
                'quarterColumn' => $qCol,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureCanManageTarget($user);

        $qCol = $this->quarterColumn();

        if ($request->has('quarter') && !$request->has($qCol)) {
            $request->merge([$qCol => $request->input('quarter')]);
        }

        if ($request->has('cuatrimestre') && $qCol !== 'cuatrimestre') {
            $request->merge([$qCol => $request->input('cuatrimestre')]);
        }

        $allowed = $this->allowedRoles();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:' . implode(',', $allowed)],
            $qCol => ['nullable', 'integer', 'between:1,11'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['role'] === 'alumno') {
            $request->validate([
                $qCol => ['required', 'integer', 'between:1,11'],
            ]);
        } else {
            $data[$qCol] = null;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = (bool) $data['is_active'];
        $user->{$qCol} = $data[$qCol];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    public function toggle(User $user)
    {
        $this->ensureCanManageTarget($user);

        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'error' => 'No puedes desactivar tu propio usuario.',
            ]);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('success', 'Estatus actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        $this->ensureCanManageTarget($user);

        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'error' => 'No puedes eliminar tu propio usuario.',
            ]);
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}