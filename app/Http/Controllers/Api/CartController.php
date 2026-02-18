<?php

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
        // Ambil user yang sedang login
        $user = Auth::user();

        // Cari keranjang milik user, dan ambil detail item beserta produknya
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Keranjang kosong',
                'data' => []
            ]);
        }

        return response()->json([
            'message' => 'Isi keranjang user',
            'data' => $cart
        ]);
    }

    // 2. TAMBAH ITEM KE KERANJANG
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::user();

        // 1. Cek User sudah punya keranjang belum? Kalau belum, buatkan.
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        // 2. Cek apakah produk ini sudah ada di keranjang?
        $existingItem = CartItem::where('cart_id', $cart->id)
                                ->where('product_id', $request->product_id)
                                ->first();

        if ($existingItem) {
            // Kalau sudah ada, tambahkan jumlahnya
            $existingItem->quantity += $request->quantity;
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
}
