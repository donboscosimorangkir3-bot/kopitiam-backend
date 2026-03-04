<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule; // Untuk validasi unique saat update
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryController extends Controller
{
    // 1. LIHAT SEMUA KATEGORI (Public & Admin) - GET /api/categories
    public function index()
    {
        $categories = Category::orderBy('name')->get();
        return response()->json([
            'message' => 'Daftar Kategori',
            'data' => $categories
        ]);
    }

    // 2. LIHAT DETAIL KATEGORI (Admin/Owner) - GET /api/admin/categories/{id}
    public function show(Category $category) // Route Model Binding
    {
        return response()->json([
            'message' => 'Detail kategori',
            'data' => $category
        ]);
    }

    // 3. TAMBAH KATEGORI BARU (Admin/Owner) - POST /api/admin/categories
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:categories,name', // Nama kategori harus unik
            // 'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Jika nanti kategori punya gambar
        ]);

        // TODO: Logika upload gambar kategori jika diperlukan
        // $imagePath = null;
        // if ($request->hasFile('image')) { ... }

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'image' => null // Untuk sementara null
        ]);

        return response()->json([
            'message' => 'Kategori berhasil dibuat',
            'data' => $category
        ], 201);
    }

    // 4. EDIT KATEGORI (Admin/Owner) - PUT /api/admin/categories/{id}
    public function update(Request $request, Category $category) // Route Model Binding
    {
        $request->validate([
            // Nama unik, tapi abaikan kategori ini sendiri
            'name' => ['required', 'string', Rule::unique('categories')->ignore($category->id)],
            // 'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Jika nanti kategori punya gambar
        ]);

        // TODO: Logika update gambar kategori jika diperlukan
        // $imagePath = $category->image;
        // if ($request->hasFile('image')) { ... }

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            // 'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui',
            'data' => $category
        ]);
    }

    // 5. HAPUS KATEGORI (Admin/Owner) - DELETE /api/admin/categories/{id}
    public function destroy(Category $category) // Route Model Binding
    {
        // PENTING: Load relasi products terlebih dahulu sebelum menghitung
        $category->load('products'); // <-- TAMBAHKAN BARIS INI

        // Cek apakah ada produk yang masih menggunakan kategori ini
        if ($category->products->count() > 0) { // <-- Gunakan ->products (koleksi yang sudah dimuat)
            return response()->json([
                'message' => 'Tidak dapat menghapus kategori karena masih ada produk terkait.',
                'products_count' => $category->products->count() // <-- Tambahkan ini untuk debugging
            ], 400); // 400 Bad Request
        }

        // TODO: Hapus gambar kategori dari storage jika ada (jika kategori punya gambar)
        // if ($category->image) {
        //     Storage::disk('public')->delete(str_replace('/storage/', '', $category->image));
        // }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus'
        ]);
    }
}
