<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // IZINKAN KOLOM INI DIISI
    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'status',
        'shipping_address',
        'shipping_note'
    ];

    // Relasi: Order milik User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Order punya banyak Item
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Relasi: Order punya satu Pembayaran
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
