<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * GET DATA SETTING (Untuk Guest & Admin)
     */
    public function index()
    {
        $settings = Setting::first();

        if (!$settings) {
            return response()->json([
                'message' => 'Daftar Pengaturan Kafe (Default)',
                'data' => [
                    'cafe_name'             => 'Kopitiam33',
                    'cafe_description'      => 'Kopitiam33 menyajikan pengalaman ngopi tradisional dengan sentuhan modern.',
                    'cafe_operation_hours'  => 'Setiap Hari: 07.00 - 22.00 WIB',
                    'cafe_address'          => 'Jl. Kopi Nikmat No. 33, Pusat Kota',
                    'cafe_phone'            => '0812-3456-7890',
                    'cafe_image'            => null,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Daftar Pengaturan Kafe',
            'data'    => $settings
        ]);
    }

    /**
     * UPDATE SETTING (Khusus Owner/Admin)
     *
     * Flutter mengirim field tambahan:
     *   delete_image = "1"  → hapus foto yang ada tanpa upload baru
     */
    public function update(Request $request)
    {
        $settings = Setting::first() ?? new Setting();

        $request->validate([
            'cafe_name'            => 'required|string',
            'cafe_description'     => 'required|string',
            'cafe_operation_hours' => 'required|string',
            'cafe_phone'           => 'required|string',
            'cafe_address'         => 'required|string',
            'image'                => 'nullable|image|mimes:jpg,png,jpeg,webp|max:4096',
            'delete_image'         => 'nullable|string', // "1" = hapus foto
        ]);

        if (!$settings->exists) {
            $settings->key = 'cafe_profile';
        }

        $settings->cafe_name            = $request->cafe_name;
        $settings->cafe_description     = $request->cafe_description;
        $settings->cafe_operation_hours = $request->cafe_operation_hours;
        $settings->cafe_phone           = $request->cafe_phone;
        $settings->cafe_address         = $request->cafe_address;

        // ── KASUS 1: Upload gambar baru ──────────────────────────────
        if ($request->hasFile('image')) {
            // Hapus foto lama jika ada
            if ($settings->cafe_image) {
                Storage::disk('public')->delete($settings->cafe_image);
            }

            $path = $request->file('image')->store('cafe', 'public');
            $settings->cafe_image = $path;

        // ── KASUS 2: Flutter minta hapus foto (tanpa upload baru) ────
        } elseif ($request->input('delete_image') === '1') {
            // Hapus file fisik dari storage
            if ($settings->cafe_image) {
                Storage::disk('public')->delete($settings->cafe_image);
            }

            // Kosongkan kolom di database
            $settings->cafe_image = null;
        }

        // ── KASUS 3: Tidak ada gambar & tidak ada delete_image ───────
        // → biarkan cafe_image tetap seperti semula (tidak diubah)

        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Informasi kafe berhasil diperbarui',
            'data'    => $settings
        ]);
    }
}
