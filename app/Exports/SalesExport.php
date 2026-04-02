<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function collection()
    {
        // payment relasi HasOne memang ada di Order model — aman di-load
        return Order::with(['user', 'items', 'payment'])
            ->whereIn('status', [
                'pending',
                'paid',
                'processing',
                'completed',
                'cancelled',
            ])
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
            'Tipe Pesanan',
            'Nomor Meja',
            'Total (Rp)',
            'Status',
            'Metode Pembayaran',
            'Tanggal Pesanan',
            'Detail Item',
        ];
    }

    public function map($order): array
    {
        // Gabungkan semua item jadi satu string
        $itemDetails = $order->items->map(function ($item) {
            return "{$item->quantity}x {$item->product_name} (Rp "
                . number_format($item->price, 0, ',', '.') . ")";
        })->implode('; ');

        // Label status dalam bahasa Indonesia
        $statusLabels = [
            'pending'    => 'Menunggu',
            'paid'       => 'Dibayar',
            'processing' => 'Diproses',
            'completed'  => 'Selesai',
            'cancelled'  => 'Dibatalkan',
        ];

        // Label tipe pesanan
        $orderTypeLabels = [
            'pickup'   => 'Pickup',
            'dine-in'  => 'Dine In',
        ];

        // ✅ FIX 3: Ganti $order->payment->payment_method dengan
        //           $order->payment_method langsung dari kolom Order
        return [
            $order->id,
            $order->order_number,
            $order->user->name  ?? 'N/A',
            $order->user->email ?? 'N/A',
            $orderTypeLabels[$order->order_type] ?? 'Pickup',
            $order->table_number ?? '-',
            number_format($order->total_amount, 0, ',', '.'),
            $statusLabels[$order->status] ?? $order->status,
            // payment_method ada di tabel payments lewat relasi $order->payment
            $order->payment?->payment_method === 'cash_on_pickup'
                ? 'Bayar di Kafe'
                : ($order->payment?->payment_method ?? 'N/A'),
            $order->created_at->format('d/m/Y H:i'),
            $itemDetails,
        ];
    }

    // Lebar kolom agar Excel rapi
    public function columnWidths(): array
    {
        return [
            'A' => 10,  // ID
            'B' => 22,  // Nomor Pesanan
            'C' => 20,  // Pelanggan
            'D' => 28,  // Email
            'E' => 14,  // Tipe Pesanan
            'F' => 12,  // Nomor Meja
            'G' => 16,  // Total
            'H' => 14,  // Status
            'I' => 18,  // Metode Bayar
            'J' => 20,  // Tanggal
            'K' => 50,  // Detail Item
        ];
    }

    // Style header row agar bold & berwarna
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '2D6A4F'], // hijau gelap
                ],
            ],
        ];
    }
}
