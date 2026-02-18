<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Penting untuk Transaksi Database
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // 1. CHECKOUT (Membuat Pesanan dari Keranjang)
    public function checkout(Request $request)
    {
        $request->validate([
            'shipping_address' => 'required|string',
            'payment_method' => 'required|in:bank_transfer,ewallet,cash',
        ]);

        $user = Auth::user();

        // Ambil keranjang user
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        // Cek apakah keranjang kosong?
        if (!$cart || $cart->items->count() == 0) {
            return response()->json(['message' => 'Keranjang kosong'], 400);
        }

        // --- MULAI TRANSAKSI DATABASE (Data Integrity) ---
        return DB::transaction(function () use ($request, $user, $cart) {

            // A. Buat Header Pesanan
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(10)), // Contoh: ORD-XJ829KS
                'total_amount' => 0, // Nanti diupdate
                'status' => 'pending',
                'shipping_address' => $request->shipping_address,
            ]);

            $totalAmount = 0;

            // B. Pindahkan Item Keranjang ke Item Pesanan
            foreach ($cart->items as $item) {
                $subtotal = $item->product->price * $item->quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name, // Snapshot nama
                    'price' => $item->product->price,       // Snapshot harga
                    'quantity' => $item->quantity,
                    'subtotal' => $subtotal
                ]);

                $totalAmount += $subtotal;
            }

            // C. Update Total Harga di Header Pesanan
            $order->update(['total_amount' => $totalAmount]);

            // D. Buat Data Pembayaran (Otomatis status pending)
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'paid_at' => null
            ]);

            // E. Kosongkan Keranjang (Hapus Cart Items)
            $cart->items()->delete();

            return response()->json([
                'message' => 'Pesanan berhasil dibuat',
                'data' => $order
            ], 201);
        });
    }

    // 2. RIWAYAT PESANAN (History User)
    public function myOrders()
    {
        $orders = Order::where('user_id', Auth::id())
                        ->with('items') // Sertakan detail item
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json(['data' => $orders]);
    }

    // 3. SEMUA PESANAN (Khusus Admin)
    public function index()
    {
        // Admin bisa lihat semua pesanan + siapa yang pesan
        $orders = Order::with(['user', 'items'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $orders]);
    }

    // 4. UPDATE STATUS PESANAN (Khusus Admin)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,processing,shipping,completed,cancelled'
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status pesanan diperbarui',
            'data' => $order
        ]);
    }
}
