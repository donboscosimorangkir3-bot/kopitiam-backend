<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- Pastikan ini di-import

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = ['cart_id', 'product_id', 'quantity'];

    // Relasi: CartItem adalah milik Cart
    public function cart(): BelongsTo // <-- PASTIKAN FUNGSI INI ADA DAN NAMANYA PERSIS 'cart'
    {
        return $this->belongsTo(Cart::class);
    }

    // Relasi: CartItem adalah milik Product
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
