<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    // 1. LIHAT ISI KERANJANG (GET /api/cart)
    public function index()
    {
        $user = Auth::user();
        // Pastikan keranjang di-load bersama items dan product detailnya
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first(); // <-- Pastikan .product ada di sini

        if (!$cart) {
            return response()->json([
                'message' => 'Keranjang kosong',
                'data' => ['items' => []]
            ]);
        }

        return response()->json([
            'message' => 'Isi keranjang user',
            'data' => $cart // Ini akan mengembalikan objek cart lengkap dengan items dan products
        ]);
    }

    // 2. TAMBAH ITEM KE KERANJANG (POST /api/cart)
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $product = Product::findOrFail($request->product_id); // Ambil detail produk

        // Cek stok sebelum menambah/mengupdate
        if ($request->quantity > $product->stock) {
            return response()->json(['message' => 'Kuantitas melebihi stok yang tersedia.'], 400);
        }

        $existingItem = CartItem::where('cart_id', $cart->id)
                                ->where('product_id', $request->product_id)
                                ->first();

        if ($existingItem) {
            // Jika sudah ada, update quantity
            $newQuantity = $existingItem->quantity + $request->quantity;
            if ($newQuantity > $product->stock) { // Cek stok lagi setelah penambahan
                return response()->json(['message' => 'Total kuantitas melebihi stok yang tersedia.'], 400);
            }
            $existingItem->quantity = $newQuantity;
            $existingItem->save();
        } else {
            // Kalau belum ada, buat item baru
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);
        }

        return response()->json([
            'message' => 'Berhasil masuk keranjang',
        ], 201);
    }

    // 3. UPDATE KUANTITAS ITEM DI KERANJANG (PATCH /api/cart/{cartItem})
    public function update(Request $request, CartItem $cartItem)
    {
        // Paksa memuat relasi 'cart' dari cartItem
        $cartItem->load('cart'); // <-- TAMBAHKAN BARIS INI

        // Pastikan item keranjang ini milik user yang sedang login
        if ($cartItem->cart->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $product = Product::findOrFail($cartItem->product_id);
        if ($request->quantity > $product->stock) {
            return response()->json(['message' => 'Kuantitas melebihi stok yang tersedia.'], 400);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Kuantitas item keranjang berhasil diupdate',
            'data' => $cartItem->load('product')
        ]);
    }

    // 4. HAPUS ITEM DARI KERANJANG (DELETE /api/cart/{cartItem})
    public function destroy(CartItem $cartItem)
    {
        // Paksa memuat relasi 'cart' dari cartItem
        $cartItem->load('cart'); // <-- TAMBAHKAN BARIS INI

        // Pastikan item keranjang ini milik user yang sedang login
        if ($cartItem->cart->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cartItem->delete();

        return response()->json(['message' => 'Item keranjang berhasil dihapus']);
    }
}
