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
     * Mendukung 2 alur:
     *   A) Dari keranjang  → cart_item_ids dikirim, items TIDAK dikirim
     *   B) Beli Sekarang   → items dikirim langsung, cart_item_ids TIDAK dikirim
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'payment_method'      => 'required|in:cash_on_pickup',
            'order_type'          => 'required|in:dine-in,pickup',
            'table_number'        => 'required_if:order_type,dine-in|nullable|string|max:10',
            'cart_item_ids'       => 'nullable|array',
            'cart_item_ids.*'     => 'nullable|integer',
            // Validasi untuk alur Beli Sekarang
            'items'               => 'nullable|array',
            'items.*.product_id'  => 'required_with:items|exists:products,id',
            'items.*.quantity'    => 'required_with:items|integer|min:1',
            'items.*.temperature' => 'nullable|string|in:hot,cold',
        ]);

        $user = Auth::user();

        // ── Tentukan alur: Beli Sekarang atau dari Keranjang ──────────
        $isBuyNow = $request->has('items')
            && is_array($request->items)
            && count($request->items) > 0
            && (!$request->has('cart_item_ids') || empty($request->cart_item_ids));
        // ─────────────────────────────────────────────────────────────

        if ($isBuyNow) {
            // ══════════════════════════════════════════════════════════
            // ALUR B: BELI SEKARANG (langsung dari product detail)
            // ══════════════════════════════════════════════════════════
            return DB::transaction(function () use ($request, $user) {

                $order = Order::create([
                    'user_id'          => $user->id,
                    'order_type'       => $request->order_type,
                    'table_number'     => $request->order_type == 'dine-in' ? $request->table_number : null,
                    'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
                    'total_amount'     => 0,
                    'status'           => 'pending',
                    'shipping_address' => $request->order_type == 'dine-in'
                        ? 'Dine In - Meja ' . $request->table_number
                        : 'Pickup di Kopitiam33',
                ]);

                $totalAmount = 0;

                foreach ($request->items as $itemData) {
                    $product = Product::find($itemData['product_id']);

                    if (!$product) {
                        throw new \Exception('Produk tidak ditemukan.');
                    }

                    if ($itemData['quantity'] > $product->stock) {
                        throw new \Exception('Stok produk ' . $product->name . ' tidak mencukupi.');
                    }

                    $temperature = $itemData['temperature'] ?? null;

                    // ✅ Harga berdasarkan suhu
                    if ($temperature === 'cold' && !is_null($product->price_cold)) {
                        $price = (float) $product->price_cold;
                    } else {
                        $price = (float) $product->price;
                    }

                    $subtotal = $price * $itemData['quantity'];

                    OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'price'        => $price,
                        'quantity'     => $itemData['quantity'],
                        'subtotal'     => $subtotal,
                        'temperature'  => $temperature,
                        'note'         => $itemData['note'] ?? null,
                    ]);

                    $product->stock -= $itemData['quantity'];
                    $product->save();

                    $totalAmount += $subtotal;
                }

                $order->update(['total_amount' => $totalAmount]);

                Payment::create([
                    'order_id'       => $order->id,
                    'payment_method' => $request->payment_method,
                    'payment_status' => 'pending',
                    'paid_at'        => null,
                ]);

                return response()->json([
                    'message' => 'Pesanan berhasil dibuat, menunggu pembayaran di kafe.',
                    'data'    => $order->load('payment'),
                ], 201);
            });

        } else {
            // ══════════════════════════════════════════════════════════
            // ALUR A: DARI KERANJANG (cart_item_ids)
            // ══════════════════════════════════════════════════════════
            $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

            if (!$cart || $cart->items->count() == 0) {
                return response()->json(['message' => 'Keranjang kosong, tidak bisa checkout.'], 400);
            }

            return DB::transaction(function () use ($request, $user, $cart) {

                $selectedIds    = $request->cart_item_ids;
                $itemsToProcess = ($selectedIds && count($selectedIds) > 0)
                    ? $cart->items->whereIn('id', $selectedIds)
                    : $cart->items;

                if ($itemsToProcess->count() == 0) {
                    throw new \Exception('Tidak ada item yang dipilih untuk checkout.');
                }

                $order = Order::create([
                    'user_id'          => $user->id,
                    'order_type'       => $request->order_type,
                    'table_number'     => $request->order_type == 'dine-in' ? $request->table_number : null,
                    'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
                    'total_amount'     => 0,
                    'status'           => 'pending',
                    'shipping_address' => $request->order_type == 'dine-in'
                        ? 'Dine In - Meja ' . $request->table_number
                        : 'Pickup di Kopitiam33',
                ]);

                $totalAmount = 0;

                foreach ($itemsToProcess as $item) {
                    if ($item->quantity > $item->product->stock) {
                        throw new \Exception('Stok produk ' . $item->product->name . ' tidak mencukupi.');
                    }

                    $temperature = $item->temperature;

                    // ✅ Harga berdasarkan suhu
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
                        'temperature'  => $temperature,
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
                    'paid_at'        => null,
                ]);

                return response()->json([
                    'message' => 'Pesanan berhasil dibuat, menunggu pembayaran di kafe.',
                    'data'    => $order->load('payment'),
                ], 201);
            });
        }
    }

    /**
     * 2. FUNGSI MELIHAT PESANAN MILIK USER
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

        $notifTitle   = "";
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

    /**
 * 6. FUNGSI BATALKAN PESANAN (CUSTOMER)
 * Hanya bisa dibatalkan saat status masih 'pending'
 * Stok produk akan dikembalikan
 */
public function cancelOrder(Request $request, Order $order)
{
    $user = Auth::user();

    // Pastikan pesanan milik user yang login
    if ($order->user_id !== $user->id) {
        return response()->json(['message' => 'Anda tidak memiliki akses ke pesanan ini.'], 403);
    }

    // Hanya bisa cancel saat pending
    if ($order->status !== 'pending') {
        return response()->json([
            'message' => 'Pesanan tidak dapat dibatalkan karena sudah diproses oleh kasir.'
        ], 400);
    }

    return DB::transaction(function () use ($order) {
        // Kembalikan stok setiap produk
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->stock += $item->quantity;
                $product->save();
            }
        }

        // Update status pesanan dan pembayaran
        $order->update(['status' => 'cancelled']);

        if ($order->payment) {
            $order->payment->update(['payment_status' => 'cancelled']);
        }

        // Kirim notifikasi ke user
        \App\Models\Notification::create([
            'user_id' => $order->user_id,
            'title'   => 'Pesanan Dibatalkan',
            'message' => "Pesanan #{$order->order_number} telah berhasil dibatalkan.",
            'type'    => 'pesanan',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data'    => $order->load('payment', 'items.product'),
        ]);
    });
}
}
