<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use Illuminate\Http\Request;

class TableController extends Controller {

    // 1. LIHAT SEMUA MEJA (Untuk Owner)
    public function index() {
        return response()->json([
            'data' => CafeTable::orderBy('number', 'asc')->get()
        ]);
    }

    // 2. LIHAT HANYA MEJA AKTIF (Untuk Customer - BARU)
    // Gunakan ini di Flutter sisi Customer agar meja rusak tidak muncul
    public function getAvailableTables() {
        return response()->json([
            'data' => CafeTable::where('is_available', true)
                               ->orderBy('number', 'asc')
                               ->get()
        ]);
    }

    // 3. TAMBAH MEJA
    public function store(Request $request) {
        $request->validate([
            'number' => 'required|unique:cafe_tables,number'
        ]);

        $table = CafeTable::create([
            'number' => $request->number,
            'is_available' => true // Default selalu aktif saat baru dibuat
        ]);

        return response()->json(['message' => 'Meja berhasil ditambah', 'data' => $table], 201);
    }

    // 4. UPDATE MEJA
public function update(Request $request, $id)
{
    $table = CafeTable::findOrFail($id);

    $validated = $request->validate([
        'number' => 'required|unique:cafe_tables,number,' . $id,
        'is_available' => 'nullable|boolean'
    ]);

    $table->update($validated);

    return response()->json([
        'message' => 'Meja berhasil diupdate',
        'data' => $table
    ]);
}

    // 5. HAPUS MEJA
    public function destroy($id) {
        $table = CafeTable::findOrFail($id);
        $table->delete();
        return response()->json(['message' => 'Meja berhasil dihapus']);
    }
}
