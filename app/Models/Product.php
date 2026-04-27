<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // --- BAGIAN INI YANG KURANG TADI ---
    protected $fillable = [
    'category_id',
    'name',
    'description',
    'price',
    'price_cold',
    'stock',
    'image_url',
    'is_available',
];

    // Relasi ke Kategori (Biar nanti bisa dipanggil)
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function getImageUrlAttribute($value)
{
    return $value ? asset('storage/' . $value) : null;
}
}
