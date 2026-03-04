<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Pastikan Carbon terimport untuk tanggal

class ReportController extends Controller
{
    // 1. GET RINGKASAN STATISTIK DASHBOARD (Owner) - GET /api/admin/reports/summary
    public function getSummary(Request $request)
    {
        // Filter berdasarkan tanggal jika ada (opsional)
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();

        // Total Omset
        $totalRevenue = Order::where('status', 'completed')
                             ->whereBetween('created_at', [$startDate, $endDate])
                             ->sum('total_amount');

        // Total Pesanan
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
                            ->count();

        // Pesanan Selesai
        $completedOrders = Order::where('status', 'completed')
                                ->whereBetween('created_at', [$startDate, $endDate])
                                ->count();

        // Produk Terlaris (Top Selling Products) - Pie Chart
        $topProducts = OrderItem::select('product_name', DB::raw('SUM(quantity) as total_quantity'))
                                ->whereHas('order', function ($query) use ($startDate, $endDate) {
                                    $query->where('status', 'completed')
                                          ->whereBetween('created_at', [$startDate, $endDate]);
                                })
                                ->groupBy('product_name')
                                ->orderByDesc('total_quantity')
                                ->limit(5)
                                ->get();

        // Penjualan Harian (untuk Bar Chart)
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
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'top_products' => $topProducts,
                'daily_sales' => $dailySales,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]
        ]);
    }

    // 2. GET LAPORAN PENJUALAN DETAIL (Owner) - GET /api/admin/reports/sales
    public function getDetailedSales(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();

        $sales = Order::with(['user', 'items.product', 'payment'])
                      ->where('status', 'completed')
                      ->whereBetween('created_at', [$startDate, $endDate])
                      ->orderBy('created_at', 'desc')
                      ->get();

        return response()->json([
            'message' => 'Laporan Penjualan Detail',
            'data' => $sales,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
    }

    // 3. EKSPOR LAPORAN (Owner) - GET /api/admin/reports/export (TODO: Implementasi file generation)
    public function exportSales(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();

        // TODO: LOGIKA GENERATE FILE EXCEL/PDF DI SINI
        // Ini akan melibatkan library seperti Maatwebsite/Excel atau Dompdf/Snappy PDF
        // Untuk sekarang, kita bisa kembalikan pesan sukses placeholder.

        return response()->json([
            'message' => "Fitur ekspor laporan untuk tanggal {$startDate->toDateString()} hingga {$endDate->toDateString()} sedang dikembangkan. File akan segera tersedia."
        ]);
    }
}
