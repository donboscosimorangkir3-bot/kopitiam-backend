<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    // IZINKAN KOLOM INI DIISI
    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_status',
        'transaction_id',
        'paid_at'
    ];

    // Relasi ke tabel Order
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
