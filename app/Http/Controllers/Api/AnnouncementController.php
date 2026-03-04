<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Untuk upload gambar

class AnnouncementController extends Controller
{
    // 1. LIHAT SEMUA PENGUMUMAN (Public & Admin) - GET /api/announcements
    public function index()
    {
        // Untuk customer, hanya tampilkan yang aktif dan belum kadaluarsa
        // Untuk admin, tampilkan semua
        $announcements = Announcement::when(!request()->user() || request()->user()->role === 'customer', function ($query) {
                                $query->where('is_active', true)
                                      ->where(function ($q) {
                                          $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                                      });
                            })
                            ->orderBy('published_at', 'desc')
                            ->get();

        return response()->json([
            'message' => 'Daftar Pengumuman',
            'data' => $announcements
        ]);
    }

    // 2. LIHAT DETAIL PENGUMUMAN (Admin/Owner) - GET /api/admin/announcements/{id}
    public function show(Announcement $announcement)
    {
        return response()->json([
            'message' => 'Detail pengumuman',
            'data' => $announcement
        ]);
    }

    // 3. TAMBAH PENGUMUMAN BARU (Admin/Owner) - POST /api/admin/announcements
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'boolean',
            'published_at' => 'nullable|date',
            'expired_at' => 'nullable|date|after_or_equal:published_at',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('announcements', 'public');
            $imagePath = config('app.url') . Storage::url($path);
        }

        $announcement = Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'image_url' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
            'published_at' => $request->published_at ?? now(),
            'expired_at' => $request->expired_at,
        ]);

        return response()->json([
            'message' => 'Pengumuman berhasil ditambahkan',
            'data' => $announcement
        ], 201);
    }

    // 4. EDIT PENGUMUMAN (Admin/Owner) - POST /api/admin/announcements/{id}
    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'boolean',
            'published_at' => 'nullable|date',
            'expired_at' => 'nullable|date|after_or_equal:published_at',
        ]);

        $imagePath = $announcement->image_url;
        if ($request->hasFile('image')) {
            if ($announcement->image_url) {
                Storage::disk('public')->delete(str_replace(config('app.url') . '/storage/', '', $announcement->image_url));
            }
            $path = $request->file('image')->store('announcements', 'public');
            $imagePath = config('app.url') . Storage::url($path);
        } else if ($request->input('clear_image') == 'true') {
            if ($announcement->image_url) {
                Storage::disk('public')->delete(str_replace(config('app.url') . '/storage/', '', $announcement->image_url));
            }
            $imagePath = null;
        }

        $announcement->update([
            'title' => $request->title,
            'content' => $request->content,
            'image_url' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
            'published_at' => $request->published_at ?? now(),
            'expired_at' => $request->expired_at,
        ]);

        return response()->json([
            'message' => 'Pengumuman berhasil diperbarui',
            'data' => $announcement
        ]);
    }

    // 5. HAPUS PENGUMUMAN (Admin/Owner) - DELETE /api/admin/announcements/{id}
    public function destroy(Announcement $announcement)
    {
        if ($announcement->image_url) {
            Storage::disk('public')->delete(str_replace(config('app.url') . '/storage/', '', $announcement->image_url));
        }
        $announcement->delete();

        return response()->json([
            'message' => 'Pengumuman berhasil dihapus'
        ]);
    }
}
