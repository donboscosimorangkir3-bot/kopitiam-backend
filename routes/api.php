<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════
// IMPORT CONTROLLERS
// ═══════════════════════════════════════════════════════
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TableController;

// ═══════════════════════════════════════════════════════
//
//  ██████╗ ██╗   ██╗██████╗ ██╗     ██╗ ██████╗
//  ██╔══██╗██║   ██║██╔══██╗██║     ██║██╔════╝
//  ██████╔╝██║   ██║██████╔╝██║     ██║██║
//  ██╔═══╝ ██║   ██║██╔══██╗██║     ██║██║
//  ██║     ╚██████╔╝██████╔╝███████╗██║╚██████╗
//  ╚═╝      ╚═════╝ ╚═════╝ ╚══════╝╚═╝ ╚═════╝
//
//  ROUTES (Tidak memerlukan autentikasi)
//
// ═══════════════════════════════════════════════════════

// ── Autentikasi ─────────────────────────────────────────────────────────────
Route::post('/register',        [AuthController::class, 'register']);
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/verify-otp',      [AuthController::class, 'verifyOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

// ── Produk & Kategori (Publik untuk tampilan menu pelanggan) ─────────────────
Route::get('/products',          [ProductController::class,  'index']);
Route::get('/products/{product}',[ProductController::class,  'show']);
Route::get('/categories',        [CategoryController::class, 'index']);

// ── Pengumuman (Publik) ──────────────────────────────────────────────────────
Route::get('/announcements', [AnnouncementController::class, 'index']);

// ── Pengaturan Kafe (Publik untuk info kafe di halaman pelanggan) ────────────
Route::get('/settings', [SettingController::class, 'index']);


// ═══════════════════════════════════════════════════════
//
//  ██████╗ ██████╗  ██████╗ ████████╗███████╗ ██████╗████████╗███████╗██████╗
//  ██╔══██╗██╔══██╗██╔═══██╗╚══██╔══╝██╔════╝██╔════╝╚══██╔══╝██╔════╝██╔══██╗
//  ██████╔╝██████╔╝██║   ██║   ██║   █████╗  ██║        ██║   █████╗  ██║  ██║
//  ██╔═══╝ ██╔══██╗██║   ██║   ██║   ██╔══╝  ██║        ██║   ██╔══╝  ██║  ██║
//  ██║     ██║  ██║╚██████╔╝   ██║   ███████╗╚██████╗   ██║   ███████╗██████╔╝
//  ╚═╝     ╚═╝  ╚═╝ ╚═════╝    ╚═╝   ╚══════╝ ╚═════╝   ╚═╝   ╚══════╝╚═════╝
//
//  ROUTES (Memerlukan login / token Sanctum)
//
// ═══════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ────────────────────────────────────────────────────────────────────────
    // USER & PROFIL
    // ────────────────────────────────────────────────────────────────────────
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout',               [AuthController::class, 'logout']);
    Route::post('/user/profile',         [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);

    // ────────────────────────────────────────────────────────────────────────
    // NOTIFIKASI PELANGGAN
    // ────────────────────────────────────────────────────────────────────────
    Route::get('/notifications', function (Request $request) {
        $notifications = \App\Models\Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $notifications]);
    });

    // ────────────────────────────────────────────────────────────────────────
    // KERANJANG BELANJA (Customer)
    // ────────────────────────────────────────────────────────────────────────
    Route::get('/cart',                [CartController::class, 'index']);
    Route::post('/cart',               [CartController::class, 'store']);
    Route::patch('/cart/{cartItem}',   [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}',  [CartController::class, 'destroy']);

    // ────────────────────────────────────────────────────────────────────────
    // CHECKOUT & RIWAYAT PESANAN (Customer)
    // ────────────────────────────────────────────────────────────────────────
    Route::post('/checkout',  [OrderController::class, 'checkout']);
    Route::get('/orders',     [OrderController::class, 'myOrders']);

    // ────────────────────────────────────────────────────────────────────────
    // MEJA — Tersedia untuk semua user yang login
    // Catatan: apiResource mencakup index, store, show, update, destroy.
    // Route GET /tables/available dan PUT /tables/{id} sudah tercakup di dalam
    // apiResource, namun didefinisikan eksplisit di bawah untuk kejelasan.
    // ────────────────────────────────────────────────────────────────────────
    Route::get('/tables/available',  [TableController::class, 'getAvailableTables']);
    Route::apiResource('tables',     TableController::class);

    // ────────────────────────────────────────────────────────────────────────
    // PENGATURAN KAFE — Update (memerlukan login, khusus owner di bawah)
    // ────────────────────────────────────────────────────────────────────────
    Route::post('/settings/update', [SettingController::class, 'update']);


    // ════════════════════════════════════════════════════════════════════════
    // ADMIN / OWNER / KASIR — Memerlukan role tertentu
    // ════════════════════════════════════════════════════════════════════════
    Route::middleware('role:admin,owner,cashier')->group(function () {

        // ── Manajemen Kategori ───────────────────────────────────────────────
        Route::get('/admin/categories',              [CategoryController::class, 'index']);
        Route::get('/admin/categories/{category}',   [CategoryController::class, 'show']);
        Route::post('/admin/categories',             [CategoryController::class, 'store']);
        Route::put('/admin/categories/{category}',   [CategoryController::class, 'update']);
        Route::delete('/admin/categories/{category}',[CategoryController::class, 'destroy']);

        // ── Manajemen Produk ─────────────────────────────────────────────────
        Route::get('/admin/products',                [ProductController::class, 'index']);
        Route::get('/admin/products/{product}',      [ProductController::class, 'show']);
        Route::post('/admin/products',               [ProductController::class, 'store']);
        // POST karena multipart/form-data (upload gambar)
        Route::post('/admin/products/{product}',     [ProductController::class, 'update']);
        Route::delete('/admin/products/{product}',   [ProductController::class, 'destroy']);

        // ── Manajemen Pesanan ────────────────────────────────────────────────
        Route::get('/admin/orders',                          [OrderController::class, 'index']);
        Route::patch('/admin/orders/{order}/status',         [OrderController::class, 'updateStatus']);
        // Kasir membuat pesanan manual (walk-in / tanpa app)
        Route::post('/admin/orders/manual',                  [OrderController::class, 'checkoutManual']);

        // ── Manajemen Pengumuman ─────────────────────────────────────────────
        Route::get('/admin/announcements',                    [AnnouncementController::class, 'index']);
        Route::get('/admin/announcements/{announcement}',     [AnnouncementController::class, 'show']);
        Route::post('/admin/announcements',                   [AnnouncementController::class, 'store']);
        // POST karena bisa ada upload gambar/banner
        Route::post('/admin/announcements/{announcement}',    [AnnouncementController::class, 'update']);
        Route::delete('/admin/announcements/{announcement}',  [AnnouncementController::class, 'destroy']);

        // ── Laporan Penjualan ────────────────────────────────────────────────
        Route::get('/admin/reports/summary',  [ReportController::class, 'getSummary']);
        Route::get('/admin/reports/sales',    [ReportController::class, 'getDetailedSales']);

        // Export Excel — tetap dalam auth:sanctum agar tidak bisa diakses publik.
        // withoutMiddleware hanya menghapus throttle agar download file besar
        // tidak terkena rate-limit, BUKAN menghapus autentikasi.
        Route::get('/admin/reports/export', [ReportController::class, 'exportSales'])
            ->withoutMiddleware(['throttle:api']);

        // ── KHUSUS OWNER ─────────────────────────────────────────────────────
        Route::middleware('role:owner')->group(function () {

            // Manajemen Staf
            Route::get('/admin/staff',           [StaffController::class, 'index']);
            Route::post('/admin/staff',          [StaffController::class, 'store']);
            Route::put('/admin/staff/{staff}',   [StaffController::class, 'update']);
            Route::delete('/admin/staff/{staff}',[StaffController::class, 'destroy']);

            // Pengaturan Informasi Kafe
            Route::post('/admin/settings', [SettingController::class, 'update']);
        });
    });
});
