<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; // <-- PASTIKAN INI DI-IMPORT

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'status',
        'shipping_address',
        'shipping_note'
    ];

    public function user(): BelongsTo // <-- PASTIKAN TIPE HINT ': BelongsTo' INI
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany // <-- PASTIKAN TIPE HINT ': HasMany' INI
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne // <-- PASTIKAN TIPE HINT ': HasOne' INI
    {
        return $this->hasOne(Payment::class);
    }
}
