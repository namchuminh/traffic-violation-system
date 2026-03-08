<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UsersRolesController extends Controller
{
    private const ROLES = [
        'admin' => 'Quản trị',
        'supervisor' => 'Giám sát viên',
        'viewer' => 'Chỉ xem',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $role = trim((string) $request->get('role', ''));

        $users = User::query()
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($role !== '' && array_key_exists($role, self::ROLES), fn($qr) => $qr->where('role', $role))
            ->orderByDesc('id')
            ->paginate(10)
            ->appends(request()->query());

        $roles = self::ROLES;

        return view('pages.users.index', compact('users', 'q', 'role', 'roles'));
    }

    public function create()
    {
        $roles = self::ROLES;

        return view('pages.users.form', [
            'mode' => 'create',
            'user' => new User(),
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $roles = array_keys(self::ROLES);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in($roles)],
        ]);

        // theo model: role không nằm trong $fillable => set trực tiếp
        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);
        $user->role = $validated['role'];
        $user->save();

        return redirect()->route('users.index')->with('success', 'Đã tạo người dùng');
    }

    public function edit(User $user)
    {
        $roles = self::ROLES;

        return view('pages.users.form', [
            'mode' => 'edit',
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $roles = array_keys(self::ROLES);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in($roles)],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Đã cập nhật người dùng');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Đã xoá người dùng');
    }
}