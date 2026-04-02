<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use Illuminate\Http\Request;

class TableController extends Controller {
    public function index() {
        return response()->json(['data' => CafeTable::orderBy('number', 'asc')->get()]);
    }

    public function store(Request $request) {
        $request->validate(['number' => 'required|unique:cafe_tables,number']);
        $table = CafeTable::create($request->all());
        return response()->json(['message' => 'Meja berhasil ditambah', 'data' => $table], 201);
    }

    public function update(Request $request, $id) {
        $table = CafeTable::findOrFail($id);
        $request->validate(['number' => 'required|unique:cafe_tables,number,' . $id]);
        $table->update($request->all());
        return response()->json(['message' => 'Meja berhasil diupdate', 'data' => $table]);
    }

    public function destroy($id) {
        CafeTable::destroy($id);
        return response()->json(['message' => 'Meja berhasil dihapus']);
    }
}
