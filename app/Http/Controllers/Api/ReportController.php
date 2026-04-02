<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    // 1. GET RINGKASAN STATISTIK DASHBOARD (Owner)
    // GET /api/admin/reports/summary
    public function getSummary(Request $request)
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfMonth();

        // Total Omset (hanya dari pesanan selesai)
        $totalRevenue = Order::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // Total semua pesanan (semua status)
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Pesanan Selesai
        $completedOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Produk Terlaris - hanya dari pesanan selesai
        $topProducts = OrderItem::select(
                'product_name',
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'completed')
                      ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        // Penjualan Harian - hanya dari pesanan selesai
        $dailySales = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total_sales')
            )
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'message' => 'Ringkasan Laporan Penjualan',
            'data' => [
                'total_revenue'    => $totalRevenue,
                'total_orders'     => $totalOrders,
                'completed_orders' => $completedOrders,
                'top_products'     => $topProducts,
                'daily_sales'      => $dailySales,
                'start_date'       => $startDate->toDateString(),
                'end_date'         => $endDate->toDateString(),
            ]
        ]);
    }

    // 2. GET LAPORAN PENJUALAN DETAIL (Owner)
    // GET /api/admin/reports/sales
    public function getDetailedSales(Request $request)
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfMonth();

        // ✅ FIX: Hapus filter status 'completed' agar semua status tampil,
        // termasuk cancelled, pending, processing, dll.
        // ✅ FIX: Hapus relasi 'payment' karena tidak ada di Order model.
        $sales = Order::with(['user', 'items.product', 'payment'])
            ->whereIn('status', [
                'pending',
                'paid',
                'processing',
                'completed',
                'cancelled',
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message'    => 'Laporan Penjualan Detail',
            'data'       => $sales,
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
        ]);
    }

    // 3. EKSPOR LAPORAN KE CSV/EXCEL (Owner)
    // GET /api/admin/reports/export
    // Tidak memerlukan package maatwebsite/excel atau phpspreadsheet
    public function exportSales(Request $request)
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfMonth();

        try {
            // Ambil data — tanpa relasi payment
            $orders = Order::with(['user', 'items'])
                ->whereIn('status', [
                    'pending', 'paid', 'processing', 'completed', 'cancelled',
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            $statusLabels = [
                'pending'    => 'Menunggu',
                'paid'       => 'Dibayar',
                'processing' => 'Diproses',
                'completed'  => 'Selesai',
                'cancelled'  => 'Dibatalkan',
            ];

            $orderTypeLabels = [
                'pickup'  => 'Pickup',
                'dine-in' => 'Dine In',
            ];

            $fileName = 'laporan_penjualan_'
                . $startDate->format('Ymd')
                . '-'
                . $endDate->format('Ymd')
                . '.csv';

            // Stream CSV langsung ke response
            $headers = [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
                'Pragma'              => 'no-cache',
                'Expires'             => '0',
            ];

            $callback = function () use ($orders, $statusLabels, $orderTypeLabels) {
                $handle = fopen('php://output', 'w');

                // BOM untuk Excel agar baca UTF-8 dengan benar
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

                // Header kolom
                fputcsv($handle, [
                    'ID Pesanan',
                    'Nomor Pesanan',
                    'Pelanggan',
                    'Email Pelanggan',
                    'Tipe Pesanan',
                    'Nomor Meja',
                    'Total (Rp)',
                    'Status',
                    'Metode Pembayaran',
                    'Tanggal Pesanan',
                    'Detail Item',
                ], ';'); // pakai semicolon agar Excel Indonesia baca dengan benar

                foreach ($orders as $order) {
                    $itemDetails = $order->items->map(function ($item) {
                        return "{$item->quantity}x {$item->product_name} "
                            . "(Rp " . number_format($item->price, 0, ',', '.') . ")";
                    })->implode(' | ');

                    $paymentLabel = match($order->payment_method) {
                        'cash_on_pickup' => 'Bayar di Kafe',
                        'transfer'       => 'Transfer Bank',
                        'qris'           => 'QRIS',
                        default          => $order->payment_method ?? 'N/A',
                    };

                    fputcsv($handle, [
                        $order->id,
                        $order->order_number,
                        $order->user->name  ?? 'N/A',
                        $order->user->email ?? 'N/A',
                        $orderTypeLabels[$order->order_type] ?? 'Pickup',
                        $order->table_number ?? '-',
                        number_format($order->total_amount, 0, ',', '.'),
                        $statusLabels[$order->status] ?? $order->status,
                        $paymentLabel,
                        $order->created_at->format('d/m/Y H:i'),
                        $itemDetails ?: '-',
                    ], ';');
                }

                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengekspor: ' . $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => basename($e->getFile()),
            ], 500);
        }
    }
}