<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // 1. LIHAT SEMUA PRODUK (Untuk Halaman Menu)
    public function index()
    {
        // Ambil semua produk beserta nama kategorinya
        $products = Product::with('category')->where('is_available', true)->get();

        return response()->json([
            'message' => 'List semua produk',
            'data' => $products
        ]);
    }

    // 2. TAMBAH PRODUK BARU (Khusus Admin)
    public function store(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            // Ubah validasi image menjadi file gambar (jpg, png, jpeg) max 2MB
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // 2. Logika Upload Gambar
        $imagePath = null;
        if ($request->hasFile('image')) {
            // Simpan ke folder 'products' di disk 'public'
            // Nanti saat deploy, ganti 'public' jadi 's3' di file .env
            $path = $request->file('image')->store('products', 'public');

            // Generate URL lengkap (http://127.0.0.1:8000/storage/products/...)
            $imagePath = url('storage/' . $path);
        }

        // 3. Simpan ke Database
        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'image_url' => $imagePath, // Simpan URL hasil generate tadi
            'is_available' => true
        ]);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product
        ], 201);
    }
}
