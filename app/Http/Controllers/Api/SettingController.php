<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Penting untuk hapus/simpan gambar

class SettingController extends Controller
{
    /**
     * GET DATA SETTING (Untuk Guest & Admin)
     */
    public function index()
    {
        // Ambil baris pertama dari tabel settings
        $settings = Setting::first();

        // Jika database masih kosong, kirim data default agar Flutter tidak error
        if (!$settings) {
            return response()->json([
                'message' => 'Daftar Pengaturan Kafe (Default)',
                'data' => [
                    'cafe_name' => 'Kopitiam33',
                    'cafe_description' => 'Kopitiam33 menyajikan pengalaman ngopi tradisional dengan sentuhan modern.',
                    'cafe_operation_hours' => 'Setiap Hari: 07.00 - 22.00 WIB',
                    'cafe_address' => 'Jl. Kopi Nikmat No. 33, Pusat Kota',
                    'cafe_phone' => '0812-3456-7890',
                    'cafe_image' => null,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Daftar Pengaturan Kafe',
            'data' => $settings
        ]);
    }

    /**
     * UPDATE SETTING (Khusus Owner/Admin)
     */
    public function update(Request $request)
    {
        // 1. Ambil data pertama atau buat objek baru jika kosong
        $settings = Setting::first() ?? new Setting();

        // 2. Validasi input
        $request->validate([
            'cafe_name' => 'required|string',
            'cafe_description' => 'required|string',
            'cafe_operation_hours' => 'required|string',
            'cafe_phone' => 'required|string',
            'cafe_address' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,png,jpeg|max:2048'
        ]);

        // FIX: Jika data baru, isi kolom 'key' agar tidak error SQL General Error 1364
        if (!$settings->exists) {
            $settings->key = 'cafe_profile'; 
        }

        // 3. Masukkan data teks ke database
        $settings->cafe_name = $request->cafe_name;
        $settings->cafe_description = $request->cafe_description;
        $settings->cafe_operation_hours = $request->cafe_operation_hours;
        $settings->cafe_phone = $request->cafe_phone;
        $settings->cafe_address = $request->cafe_address;

        // 4. Proses Upload Gambar jika ada
        if ($request->hasFile('image')) {
            // Hapus foto lama dari storage jika ada
            if ($settings->cafe_image) {
                Storage::disk('public')->delete($settings->cafe_image);
            }
            
            // Simpan foto baru ke folder 'public/cafe'
            $path = $request->file('image')->store('cafe', 'public');
            $settings->cafe_image = $path;
        }

        // 5. Simpan ke Database
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Informasi kafe berhasil diperbarui',
            'data' => $settings
        ]);
    } // <--- KURUNG TUTUP UNTUK FUNGSI UPDATE
}