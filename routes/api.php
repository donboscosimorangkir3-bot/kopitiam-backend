<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;

// --- PUBLIC ROUTES (Tanpa Login) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']); // Lihat menu

// --- PROTECTED ROUTES (Wajib Login/Punya Token) ---
Route::middleware('auth:sanctum')->group(function () {

// FITUR ORDER (User)
    Route::post('/checkout', [OrderController::class, 'checkout']); // Bikin pesanan
    Route::get('/orders', [OrderController::class, 'myOrders']);    // Lihat riwayat

    // FITUR ORDER (Admin) - Nanti bisa dikasih middleware tambahan
    Route::get('/admin/orders', [OrderController::class, 'index']); // Lihat semua order masuk
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']); // Ganti status (misal: kirim barang)

// FITUR KERANJANG
    Route::get('/cart', [CartController::class, 'index']); // Lihat keranjang
    Route::post('/cart', [CartController::class, 'store']); // Tambah ke keranjang

    // Cek User
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin Tambah Produk (INI YANG HILANG TADI)
    Route::post('/products', [ProductController::class, 'store'])->middleware('role:admin');

});
