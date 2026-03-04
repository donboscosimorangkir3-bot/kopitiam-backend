<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    // IZINKAN KOLOM INI DIISI
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'quantity',
        'subtotal'
    ];

    public function cart(): BelongsTo // <-- TAMBAHKAN TIPE HINT ': BelongsTo'
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo // <-- TAMBAHKAN TIPE HINT ': BelongsTo'
    {
        return $this->belongsTo(Product::class);
    }
}
