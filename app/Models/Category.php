<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image'
    ];

    // Relasi: Kategori memiliki banyak produk
    public function products(): HasMany // <-- PASTIKAN FUNGSI INI ADA DAN NAMANYA PERSIS 'products'
    {
        return $this->hasMany(Product::class);
    }
}
