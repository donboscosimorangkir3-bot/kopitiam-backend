<?php
// app/Http/Controllers/Api/CartController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // 1. LIHAT ISI KERANJANG
    public function index()
    {
        $user = Auth::user();
        $cart = Cart::with('items.product')
            ->where('user_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Keranjang kosong',
                'data'    => ['items' => [], 'total_price' => 0]
            ]);
        }

        // --- TAMBAHAN LOGIKA: Hitung harga asli per item & total ---
        $totalPrice = 0;

        $cart->items->map(function ($item) use (&$totalPrice) {
            // Tentukan harga satuan berdasarkan suhu
            $unitPrice = ($item->temperature === 'cold' && $item->product->price_cold)
                ? $item->product->price_cold
                : $item->product->price;

            $item->price_at_moment = $unitPrice; // Harga satuan saat ini
            $item->subtotal = $unitPrice * $item->quantity;
            $totalPrice += $item->subtotal;

            return $item;
        });

        return response()->json([
            'message' => 'Isi keranjang user',
            'data'    => [
                'id' => $cart->id,
                'items' => $cart->items,
                'total_price' => $totalPrice // Flutter tinggal pakai nilai ini
            ]
        ]);
    }

    // 2. TAMBAH ITEM KE KERANJANG
    public function store(Request $request)
    {
        $request->validate([
            'product_id'  => 'required|exists:products,id',
            'quantity'    => 'required|integer|min:1',
            'temperature' => 'nullable|in:hot,cold',
        ]);

        $user    = Auth::user();
        $cart    = Cart::firstOrCreate(['user_id' => $user->id]);
        $product = Product::findOrFail($request->product_id);

        // --- TAMBAHAN VALIDASI: Cek apakah produk mendukung Cold ---
        if ($request->temperature === 'cold' && is_null($product->price_cold)) {
            return response()->json([
                'message' => 'Produk ini tidak tersedia dalam varian dingin.'
            ], 400);
        }

        if ($request->quantity > $product->stock) {
            return response()->json([
                'message' => 'Kuantitas melebihi stok yang tersedia.'
            ], 400);
        }

        // Cari item yang sama (ID & Suhu sama)
        $query = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id);

        if ($request->temperature) {
            $query->where('temperature', $request->temperature);
        } else {
            $query->whereNull('temperature');
        }

        $existingItem = $query->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $request->quantity;
            if ($newQty > $product->stock) {
                return response()->json([
                    'message' => 'Total kuantitas melebihi stok.'
                ], 400);
            }
            $existingItem->update(['quantity' => $newQty]);
        } else {
            CartItem::create([
                'cart_id'     => $cart->id,
                'product_id'  => $request->product_id,
                'quantity'    => $request->quantity,
                'temperature' => $request->temperature,
            ]);
        }

        return response()->json([
            'message' => 'Berhasil masuk keranjang',
        ], 201);
    }

    // 3. UPDATE KUANTITAS
    public function update(Request $request, CartItem $cartItem)
    {
        $cartItem->load('cart');

        if ($cartItem->cart->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['quantity' => 'required|integer|min:1']);

        $product = Product::findOrFail($cartItem->product_id);
        if ($request->quantity > $product->stock) {
            return response()->json([
                'message' => 'Kuantitas melebihi stok yang tersedia.'
            ], 400);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Kuantitas item keranjang berhasil diupdate',
            'data'    => $cartItem->load('product'),
        ]);
    }

    // 4. HAPUS ITEM
    public function destroy(CartItem $cartItem)
    {
        $cartItem->load('cart');

        if ($cartItem->cart->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Item keranjang berhasil dihapus'
        ]);
    }
}
