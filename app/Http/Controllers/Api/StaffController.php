<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    // 1. LIHAT SEMUA STAF (Owner) - GET /api/admin/staff
    public function index()
    {
        // Hanya tampilkan role admin dan cashier
        $staff = User::whereIn('role', ['admin', 'cashier'])->orderBy('name')->get();
        return response()->json(['data' => $staff]);
    }

    // 2. TAMBAH STAF BARU (Owner) - POST /api/admin/staff
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // Password + konfirmasi
            'phone' => 'required|string|max:255',
            'role' => 'required|in:admin,cashier', // Hanya boleh membuat admin atau cashier
        ]);

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'Staf berhasil ditambahkan.',
            'data' => $staff,
        ], 201);
    }

    // 3. EDIT STAF (Owner) - PUT /api/admin/staff/{id}
    public function update(Request $request, User $staff) // Gunakan User sebagai parameter
    {
        // Owner tidak bisa mengedit owner lain atau dirinya sendiri di sini
        if ($staff->role == 'owner' || $staff->id === Auth::id()) {
            return response()->json(['message' => 'Aksi tidak diizinkan.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'phone' => 'required|string|max:255',
            'role' => 'required|in:admin,cashier',
            'password' => 'nullable|string|min:8|confirmed', // Password opsional saat edit
        ]);

        $staff->name = $request->name;
        $staff->email = $request->email;
        $staff->phone = $request->phone;
        $staff->role = $request->role;

        if ($request->filled('password')) { // Jika ada password baru
            $staff->password = Hash::make($request->password);
        }

        $staff->save();

        return response()->json([
            'message' => 'Staf berhasil diperbarui.',
            'data' => $staff,
        ]);
    }

    // 4. HAPUS STAF (Owner) - DELETE /api/admin/staff/{id}
    public function destroy(User $staff) // Gunakan User sebagai parameter
    {
        // Owner tidak bisa menghapus owner lain atau dirinya sendiri
        if ($staff->role == 'owner' || $staff->id === Auth::id()) {
            return response()->json(['message' => 'Aksi tidak diizinkan.'], 403);
        }

        $staff->delete();

        return response()->json(['message' => 'Staf berhasil dihapus.']);
    }
}
