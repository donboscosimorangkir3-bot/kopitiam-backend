<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // --- BAGIAN INI YANG KURANG TADI ---
    // Kita harus mendaftar kolom mana saja yang boleh diisi via API
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'image_url',
        'is_available'
    ];

    // Relasi ke Kategori (Biar nanti bisa dipanggil)
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
