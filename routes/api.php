<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- DAFTAR IMPORT CONTROLLER ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SettingController;

// ═══════════════════════════════════════════════════════
// PUBLIC ROUTES (Bisa diakses tanpa login)
// ═══════════════════════════════════════════════════════
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/settings', [SettingController::class, 'index']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ═══════════════════════════════════════════════════════
// PROTECTED ROUTES (Harus Login / Punya Token)
// ═══════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ── User Info & Auth ────────────────────────────
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);

    Route::get('/tables/available', [TableController::class, 'getAvailableTables']);

    // Route untuk Kasir membuat pesanan manual
    Route::post('/admin/orders/manual',[\App\Http\Controllers\Api\OrderController::class, 'checkoutManual']);

    // ── Keranjang Belanja (Customer) ────────────────
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

    // ── Checkout & Pesanan (Customer) ───────────────
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'myOrders']);

    // TAMBAHKAN INI:
    Route::get('/notifications', function (Illuminate\Http\Request $request) {
        $notifications = \App\Models\Notification::where('user_id', $request->user()->id)
                            ->orderBy('created_at', 'desc')
                            ->get();
        return response()->json([
            'data' => $notifications
            ]);
    });

    Route::apiResource('tables', App\Http\Controllers\Api\TableController::class);

    // URL harus /settings/update dan metodenya POST (karena kirim gambar)
    Route::post('/settings/update', [SettingController::class, 'update']);



    // ═══════════════════════════════════════════════
    // ADMIN / OWNER / KASIR ROUTES
    // ═══════════════════════════════════════════════
    Route::middleware('role:admin,owner,cashier')->group(function () {

        // ── Manajemen Kategori ──────────────────────
        Route::get('/admin/categories', [CategoryController::class, 'index']);
        Route::get('/admin/categories/{category}', [CategoryController::class, 'show']);
        Route::post('/admin/categories', [CategoryController::class, 'store']);
        Route::put('/admin/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy']);

        // ── Manajemen Produk ────────────────────────
        Route::get('/admin/products', [ProductController::class, 'index']);
        Route::get('/admin/products/{product}', [ProductController::class, 'show']);
        Route::post('/admin/products', [ProductController::class, 'store']);
        Route::post('/admin/products/{product}', [ProductController::class, 'update']); // POST karena multipart/form-data
        Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);

        // ── Manajemen Pesanan ───────────────────────
        Route::get('/admin/orders', [OrderController::class, 'index']);
        Route::patch('/admin/orders/{order}/status', [OrderController::class, 'updateStatus']);

        // ── Manajemen Pengumuman ────────────────────
        Route::get('/admin/announcements', [AnnouncementController::class, 'index']);
        Route::get('/admin/announcements/{announcement}', [AnnouncementController::class, 'show']);
        Route::post('/admin/announcements', [AnnouncementController::class, 'store']);
        Route::post('/admin/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/admin/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        // ── Laporan Penjualan ───────────────────────
        Route::get('/admin/reports/summary', [ReportController::class, 'getSummary']);
        Route::get('/admin/reports/sales', [ReportController::class, 'getDetailedSales']);

        // FIX: Route ekspor dipisah dengan withoutMiddleware JSON
        // agar response binary Excel tidak di-wrap jadi JSON
        Route::get('/admin/reports/export', [ReportController::class, 'exportSales'])
            ->withoutMiddleware(['throttle:api']);

        // ── Manajemen Staf (Khusus Owner) ──────────
        Route::middleware('role:owner')->group(function () {
            Route::get('/admin/staff', [StaffController::class, 'index']);
            Route::post('/admin/staff', [StaffController::class, 'store']);
            Route::put('/admin/staff/{staff}', [StaffController::class, 'update']);
            Route::delete('/admin/staff/{staff}', [StaffController::class, 'destroy']);


         // Manajemen Informasi Kafe (Owner)
        Route::post('/admin/settings', [SettingController::class, 'update'])->middleware('role:owner'); // <-- TAMBAHKAN INI (Hanya Owner)
        });
    });
});
