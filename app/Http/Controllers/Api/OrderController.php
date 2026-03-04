<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // =====================================================
    // 1. CHECKOUT (Membuat Pesanan dari Keranjang)
    // POST /api/checkout
    // =====================================================
    public function checkout(Request $request)
    {
        $request->validate([
            'shipping_address' => 'required|string',
            'payment_method'   => 'required|in:cash_on_pickup',
        ]);

        $user = Auth::user();

        $cart = Cart::with('items.product')
                    ->where('user_id', $user->id)
                    ->first();

        if (!$cart || $cart->items->count() == 0) {
            return response()->json([
                'message' => 'Keranjang kosong'
            ], 400);
        }

        return DB::transaction(function () use ($request, $user, $cart) {

            // Membuat order baru
            $order = Order::create([
                'user_id'          => $user->id,
                'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
                'total_amount'     => 0,
                'status'           => 'pending',
                'shipping_address' => $request->shipping_address,
            ]);

            $totalAmount = 0;

            foreach ($cart->items as $item) {

                $subtotal = $item->product->price * $item->quantity;

                // Cek stok
                if ($item->quantity > $item->product->stock) {
                    throw new \Exception(
                        'Stok produk ' . $item->product->name . ' tidak mencukupi.'
                    );
                }

                // Simpan item pesanan
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name,
                    'price'        => $item->product->price,
                    'quantity'     => $item->quantity,
                    'subtotal'     => $subtotal
                ]);

                // Kurangi stok produk
                $product = Product::find($item->product_id);
                $product->stock -= $item->quantity;
                $product->save();

                $totalAmount += $subtotal;
            }

            // Update total harga
            $order->update([
                'total_amount' => $totalAmount
            ]);

            // Membuat data payment
            Payment::create([
                'order_id'        => $order->id,
                'payment_method'  => $request->payment_method,
                'payment_status'  => 'pending',
                'paid_at'         => null
            ]);

            // Kosongkan keranjang
            $cart->items()->delete();

            return response()->json([
                'message' => 'Pesanan berhasil dibuat, menunggu pembayaran di kafe.',
                'data'    => $order->load('payment')
            ], 201);
        });
    }


    // =====================================================
    // 2. RIWAYAT PESANAN USER
    // GET /api/orders
    // =====================================================
    public function myOrders()
    {
        $orders = Order::where('user_id', Auth::id())
                        ->with(['items.product', 'payment'])
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'data' => $orders
        ]);
    }


    // =====================================================
    // 3. SEMUA PESANAN (Admin/Kasir/Owner)
    // GET /api/admin/orders
    // =====================================================
    public function index()
    {
        // Pastikan semua relasi diload agar Flutter menerima data lengkap
        $orders = Order::with(['user', 'items.product', 'payment'])
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'data' => $orders
        ]);
    }


    // =====================================================
    // 4. UPDATE STATUS PESANAN
    // PATCH /api/orders/{id}/status
    // =====================================================
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,processing,shipping,completed,cancelled'
        ]);

        // Opsional: cek role user
        /*
        if (Auth::user()->role === 'customer') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        */

        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Update status order
        $order->update([
            'status' => $newStatus
        ]);

        // Update status payment jika order dibayar
        if ($order->payment && $newStatus == 'paid' && $order->payment->payment_status != 'success') {
            $order->payment->update([
                'payment_status' => 'success',
                'paid_at'        => now(),
            ]);
        }

        // Load semua relasi sebelum dikirim ke Flutter
        $order->load(['user', 'items.product', 'payment']);

        return response()->json([
            'message' => "Status pesanan #{$order->order_number} diperbarui dari {$oldStatus} ke {$newStatus}",
            'data'    => $order
        ]);
    }
}
