<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('pages.roles.index', [
            'title' => 'Manajemen Role',
            'roles' => Role::query()->withCount(['users', 'permissions'])->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return $this->form(new Role(), 'Tambah Role');
    }

    public function store(Request $request)
    {
        $data = $this->validateRole($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $role = Role::query()->create($data);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Role berhasil dibuat.');
    }

    public function edit(Role $role)
    {
        return $this->form($role->load('permissions'), 'Ubah Role');
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validateRole($request, $role);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        if ($role->slug === 'super-admin') {
            $data['slug'] = 'super-admin';
        }

        $role->update($data);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->slug === 'super-admin', 422, 'Role Super Admin tidak dapat dihapus.');
        abort_if($role->users()->exists(), 422, 'Role masih digunakan oleh user.');
        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }

    private function form(Role $role, string $title)
    {
        return view('pages.roles.form', [
            'title' => $title,
            'role' => $role,
            'permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('roles')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }
}
