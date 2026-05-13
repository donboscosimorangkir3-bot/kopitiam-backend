<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    // 1. LIHAT SEMUA PRODUK (Public & Admin) - GET /api/products
    public function index()
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'List semua produk',
            'data' => $products
        ]);
    }

    // 2. LIHAT DETAIL PRODUK (Admin/Owner) - GET /api/admin/products/{id}
    public function show(Product $product)
    {
        $product->load('category');

        return response()->json([
            'message' => 'Detail produk',
            'data' => $product
        ]);
    }

    // 3. TAMBAH PRODUK BARU (Admin/Owner) - POST /api/admin/products
    public function store(Request $request)
{
    $request->validate([
        'category_id' => 'required|exists:categories,id',
        'name' => 'required|string|max:255|unique:products,name',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'price_cold' => 'nullable|numeric|min:0',
        'stock' => 'required|integer|min:0',
        'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $imagePath = null;

    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('products', 'public');
    }

    $product = Product::create([
        'category_id' => $request->category_id,
        'name' => $request->name,
        'description' => $request->description,
        'price' => $request->price,
        'price_cold' => $request->price_cold,
        'stock' => $request->stock,
        'image_url' => $imagePath, //
        'is_available' => true
    ]);

    //  generate URL untuk response
    $product->image_url = $product->image_url
        ? asset('storage/' . $product->image_url)
        : null;

    return response()->json([
        'message' => 'Produk berhasil ditambahkan',
        'data' => $product
    ], 201);
}

    // 4. EDIT PRODUK (Admin/Owner) - POST /api/admin/products/{id}
public function update(Request $request, Product $product)
{
    $request->validate([
        'category_id' => 'required|exists:categories,id',
        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('products')->ignore($product->id)
        ],
        'description' => 'nullable|string',
        'price'       => 'required|numeric|min:0',
        'price_cold'  => 'nullable|numeric|min:0',
        'stock'       => 'required|integer|min:0',
        'image'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    // ✅ Simpan path lama sebagai default
    $imagePath = $product->getRawOriginal('image_url') ?? $product->image_url;

    // Jika image_url di DB sudah berupa full URL (bug lama), ekstrak path-nya
    if ($imagePath && str_contains($imagePath, '/storage/')) {
        $imagePath = preg_replace('/^.*\/storage\//', '', $imagePath);
    }

    // ✅ Upload gambar baru — hapus yang lama dulu
    if ($request->hasFile('image')) {
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
        $imagePath = $request->file('image')->store('products', 'public');
    }

    // ✅ Hapus gambar (user klik "Hapus Gambar Lama")
    if ($request->input('clear_image') === 'true') {
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
        $imagePath = null;
    }

    // ✅ Update database — simpan PATH RELATIF saja, bukan full URL
    $product->update([
        'category_id'  => $request->category_id,
        'name'         => $request->name,
        'description'  => $request->description,
        'price'        => $request->price,
        'price_cold'   => $request->price_cold,
        'stock'        => $request->stock,
        'image_url'    => $imagePath, // ← path relatif: "products/abc.jpg"
        'is_available' => $request->boolean('is_available', true),
    ]);

    // ✅ Refresh dari database agar data response akurat
    $product->refresh();

    // ✅ Generate full URL hanya untuk response, tidak disimpan ke DB
    $responseData = $product->toArray();
    $responseData['image_url'] = $product->image_url
        ? asset('storage/' . $product->image_url)
        : null;

    return response()->json([
        'message' => 'Produk berhasil diperbarui',
        'data'    => $responseData,
    ]);
}

    // 5. HAPUS PRODUK (Admin/Owner) - DELETE /api/admin/products/{id}
    public function destroy(Product $product)
{
    if ($product->image_url) {
        Storage::disk('public')->delete($product->image_url); // langsung
    }

    $product->delete();

    return response()->json([
        'message' => 'Produk berhasil dihapus'
    ]);
}
}
