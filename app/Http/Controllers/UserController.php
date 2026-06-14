<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('pages.users.index', [
            'title' => 'Manajemen User',
            'users' => User::query()->with('roles')->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('pages.users.form', [
            'title' => 'Tambah User',
            'user' => new User(),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateUser($request);
        $user = User::query()->create($data);
        $user->roles()->sync($request->input('roles', []));

        return redirect()->route('users.index')->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('pages.users.form', [
            'title' => 'Ubah User',
            'user' => $user->load('roles'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);

        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $user->update($data);
        $roles = $request->input('roles', []);

        if ($request->user()->is($user) && $user->isSuperAdmin()) {
            $roles[] = Role::query()->where('slug', 'super-admin')->value('id');
        }

        $user->roles()->sync(array_filter(array_unique($roles)));

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'User yang sedang login tidak dapat dihapus.');
        $user->delete();

        return back()->with('success', 'User berhasil dihapus.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);
    }
}
