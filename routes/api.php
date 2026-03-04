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

// --- PUBLIC ROUTES (Bisa diakses tanpa login) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']); // Melihat semua produk
Route::get('/products/{product}', [ProductController::class, 'show']); // Detail produk (opsional public)
Route::get('/categories', [CategoryController::class, 'index']); // Melihat semua kategori
Route::get('/announcements', [AnnouncementController::class, 'index']); // Melihat semua pengumuman

// --- PROTECTED ROUTES (Harus Login / Punya Token) ---
Route::middleware('auth:sanctum')->group(function () {

    // Informasi User Login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // KERANJANG BELANJA (Customer)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

    // CHECKOUT (Customer)
    Route::post('/checkout', [OrderController::class, 'checkout']);

    // RIWAYAT PESANAN (Customer)
    Route::get('/orders', [OrderController::class, 'myOrders']);

    // --- ADMIN / OWNER / KASIR ROUTES (Perlu Middleware Role) ---
    // Pastikan middleware role:admin terpasang untuk manajemen
    Route::middleware('role:admin,owner,cashier')->group(function () {
        // Manajemen Kategori
        Route::get('/admin/categories', [CategoryController::class, 'index']); // Read (daftar)
        Route::get('/admin/categories/{category}', [CategoryController::class, 'show']); // Read (detail)
        Route::post('/admin/categories', [CategoryController::class, 'store']); // Create
        Route::put('/admin/categories/{category}', [CategoryController::class, 'update']); // Update (gunakan PUT)
        Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy']); // Delete

        // Manajemen Produk (CRUD Lengkap)
        Route::get('/admin/products', [ProductController::class, 'index']); // Melihat semua produk
        Route::get('/admin/products/{product}', [ProductController::class, 'show']); // Melihat detail produk
        Route::post('/admin/products', [ProductController::class, 'store']); // Menambah produk
        // POST method karena bisa ada file upload (PUT/PATCH tidak support multipart/form-data)
        Route::post('/admin/products/{product}', [ProductController::class, 'update']); // Mengedit produk
        Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']); // Menghapus produk

        // Manajemen Pesanan
        Route::get('/admin/orders', [OrderController::class, 'index']);
        Route::patch('/admin/orders/{order}/status', [OrderController::class, 'updateStatus']); // Perbaiki URL PATCH

        // Manajemen Pengumuman/Promosi (CRUD Lengkap) - UNTUK OWNER/ADMIN
        Route::get('/admin/announcements', [AnnouncementController::class, 'index']); // Read (daftar semua)
        Route::get('/admin/announcements/{announcement}', [AnnouncementController::class, 'show']); // Read (detail)
        Route::post('/admin/announcements', [AnnouncementController::class, 'store']); // Create
        Route::post('/admin/announcements/{announcement}', [AnnouncementController::class, 'update']); // Update (via POST untuk file)
        Route::delete('/admin/announcements/{announcement}', [AnnouncementController::class, 'destroy']); // Delete

        // Laporan & Statistik Penjualan (Owner)
        Route::get('/admin/reports/summary', [ReportController::class, 'getSummary']);
        Route::get('/admin/reports/sales', [ReportController::class, 'getDetailedSales']);
        Route::get('/admin/reports/export', [ReportController::class, 'exportSales']);

        // TODO: Tambahkan API untuk Laporan & Statistik di sini
    });

});
