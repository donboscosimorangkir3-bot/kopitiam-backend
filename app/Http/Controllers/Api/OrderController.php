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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * 1. FUNGSI CHECKOUT (USER)
     * Membuat pesanan dari cart, menghitung total,
     * mengurangi stok, dan membuat data pembayaran (pending)
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:cash_on_pickup',
            'order_type'     => 'required|in:dine-in,pickup',
            'table_number'   => 'required_if:order_type,dine-in|nullable|string|max:10',
        ]);

        $user = Auth::user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->count() == 0) {
            return response()->json(['message' => 'Keranjang kosong, tidak bisa checkout.'], 400);
        }

        return DB::transaction(function () use ($request, $user, $cart) {

            $order = Order::create([
                'user_id'          => $user->id,
                'order_type'       => $request->order_type,
                'table_number'     => $request->order_type == 'dine-in' ? $request->table_number : null,
                'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
                'total_amount'     => 0,
                'status'           => 'pending',
                'shipping_address' => 'Pickup di Kopitiam33',
            ]);

            $totalAmount = 0;

            foreach ($cart->items as $item) {
                if ($item->quantity > $item->product->stock) {
                    throw new \Exception('Stok produk ' . $item->product->name . ' tidak mencukupi.');
                }

                $temperature = $item->temperature;

if ($temperature === 'cold' && !is_null($item->product->price_cold)) {
    $price = (float) $item->product->price_cold;
} else {
    $price = (float) $item->product->price;
}

                $subtotal = $price * $item->quantity;

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name,
                    'price'        => $price,
                    'quantity'     => $item->quantity,
                    'subtotal'     => $subtotal,
                    'temperature'  => $temperature, // TAMBAH INI
                    'note'         => $item->note ?? null,
                ]);

                $product = Product::find($item->product_id);
                $product->stock -= $item->quantity;
                $product->save();

                $totalAmount += $subtotal;
            }

            $order->update(['total_amount' => $totalAmount]);

            Payment::create([
                'order_id'       => $order->id,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'paid_at'        => null
            ]);

            $cart->items()->delete();

            return response()->json([
                'message' => 'Pesanan berhasil dibuat, menunggu pembayaran di kafe.',
                'data'    => $order->load('payment')
            ], 201);
        });
    }

    /**
     * 2. FUNGSI MELIHAT PESANAN MILIK USER
     * Menampilkan semua pesanan berdasarkan user login
     */
    public function myOrders()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['items.product', 'payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * 3. FUNGSI MELIHAT SEMUA PESANAN (ADMIN)
     * Menampilkan seluruh data pesanan dari semua user
     */
    public function index()
    {
        $orders = Order::with(['user', 'items.product', 'payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * 4. FUNGSI UPDATE STATUS PESANAN
     * Mengubah status pesanan, update pembayaran,
     * dan mengirim notifikasi ke user
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,ready,completed,cancelled'
        ]);

        $oldStatus = $order->status;
        $newStatus = $request->status;

        $order->update(['status' => $newStatus]);

        if ($order->payment && $newStatus == 'processing' && $order->payment->payment_status != 'success') {
            $order->payment->update(['payment_status' => 'success', 'paid_at' => now()]);
        }

        $notifTitle = "";
        $notifMessage = "";

        switch ($newStatus) {
            case 'processing':
                $notifTitle   = "Pembayaran Diterima";
                $notifMessage = "Pesanan #{$order->order_number} sedang disiapkan.";
                break;
            case 'ready':
                $notifTitle   = "Pesanan Siap";
                $notifMessage = "Pesanan sudah siap diambil.";
                break;
            case 'completed':
                $notifTitle   = "Pesanan Selesai";
                $notifMessage = "Terima kasih telah memesan.";
                break;
            case 'cancelled':
                $notifTitle   = "Pesanan Dibatalkan";
                $notifMessage = "Pesanan #{$order->order_number} dibatalkan.";
                break;
        }

        if ($notifTitle != "") {
            \App\Models\Notification::create([
                'user_id' => $order->user_id,
                'title'   => $notifTitle,
                'message' => $notifMessage,
                'type'    => 'pesanan',
                'is_read' => false,
            ]);
        }

        $order->load(['user', 'items.product', 'payment']);

        return response()->json([
            'message' => "Status pesanan #{$order->order_number} diperbarui dari {$oldStatus} ke {$newStatus}",
            'data'    => $order
        ]);
    }

    /**
     * 5. FUNGSI CHECKOUT MANUAL (ADMIN/KASIR)
     * Membuat pesanan langsung tanpa cart untuk customer walk-in
     */
    public function checkoutManual(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['owner', 'admin', 'cashier'])) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk membuat pesanan manual.'], 403);
        }

        $request->validate([
            'customer_name'      => 'required|string|max:255',
            'order_type'         => 'required|in:dine-in,pickup',
            'table_number'       => 'nullable|string|max:10',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {

            $order = Order::create([
                'user_id'          => Auth::id(),
                'order_type'       => $request->order_type,
                'table_number'     => $request->order_type == 'dine-in' ? $request->table_number : null,
                'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
                'total_amount'     => 0,
                'status'           => 'processing',
                'shipping_address' => 'Walk-in: ' . $request->customer_name,
            ]);

            $totalAmount = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if ($item['quantity'] > $product->stock) {
                    throw new \Exception('Stok produk ' . $product->name . ' tidak mencukupi.');
                }

                $subtotal = $product->price * $item['quantity'];

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $product->price,
                    'quantity'     => $item['quantity'],
                    'subtotal'     => $subtotal,
                    'note'         => $item['note'] ?? null,
                ]);

                $product->stock -= $item['quantity'];
                $product->save();

                $totalAmount += $subtotal;
            }

            $order->update(['total_amount' => $totalAmount]);

            Payment::create([
                'order_id'       => $order->id,
                'payment_method' => 'cash',
                'payment_status' => 'success',
                'paid_at'        => now()
            ]);

            return response()->json([
                'message' => 'Pesanan manual berhasil dibuat',
                'data'    => $order->load('payment', 'items.product')
            ], 201);
        });
    }
}
