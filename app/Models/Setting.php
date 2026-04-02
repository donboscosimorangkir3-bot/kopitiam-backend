<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    // Pastikan semua kolom ini terdaftar agar tidak Error 500
    protected $fillable = [
    'key',
    'cafe_name',
    'cafe_description',
    'cafe_operation_hours',
    'cafe_phone',
    'cafe_address',
    'cafe_image'
];
}
