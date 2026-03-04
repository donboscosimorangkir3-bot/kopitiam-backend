<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- Pastikan ini di-import
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- Pastikan ini di-import

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id'];

    // Relasi: Keranjang punya banyak Item
    public function items(): HasMany // <-- PASTIKAN FUNGSI INI ADA
    {
        return $this->hasMany(CartItem::class);
    }

    // Relasi: Keranjang milik User
    public function user(): BelongsTo // <-- PASTIKAN FUNGSI INI ADA
    {
        return $this->belongsTo(User::class);
    }
}
