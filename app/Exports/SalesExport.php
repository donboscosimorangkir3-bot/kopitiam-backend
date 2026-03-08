<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Hanya ambil order yang statusnya 'completed' untuk laporan penjualan
        return Order::with(['user', 'items', 'payment'])
                      ->where('status', 'completed') // <-- Pastikan hanya completed
                      ->whereBetween('created_at', [$this->startDate, $this->endDate])
                      ->orderBy('created_at', 'desc')
                      ->get();
    }

    public function headings(): array
    {
        return [
            'ID Pesanan',
            'Nomor Pesanan',
            'Pelanggan',
            'Email Pelanggan',
            'Total Jumlah',
            'Status',
            'Metode Pembayaran',
            'Status Pembayaran',
            'Tanggal Pesanan',
            'Detail Item',
        ];
    }

    public function map($order): array
    {
        $itemDetails = $order->items->map(function ($item) {
            return "{$item->quantity}x {$item->product_name} (Rp " . number_format($item->price, 0, ',', '.') . ")";
        })->implode('; '); // Gabungkan semua item menjadi satu string

        return [
            $order->id,
            $order->order_number,
            $order->user->name ?? 'N/A',
            $order->user->email ?? 'N/A',
            number_format($order->total_amount, 0, ',', '.'),
            $order->status,
            $order->payment->payment_method ?? 'N/A',
            $order->payment->payment_status ?? 'N/A',
            $order->created_at->format('Y-m-d H:i:s'),
            $itemDetails,
        ];
    }
}
