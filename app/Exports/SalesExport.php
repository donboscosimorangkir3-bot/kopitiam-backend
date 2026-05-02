<?php

namespace App\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        // Pastikan format tanggal benar (dari awal hari sampai akhir hari)
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate   = Carbon::parse($endDate)->endOfDay();
    }

    public function collection()
    {
        // Eager Loading relasi untuk mencegah N+1 Query (Performa Cepat)
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
        // Gabungkan semua item jadi satu string yang rapi
        $itemDetails = $order->items->map(function ($item) {
            return "{$item->quantity}x {$item->product_name} (@Rp " . number_format($item->price, 0, ',', '.') . ")";
        })->implode("\n"); // Menggunakan newline agar bisa dibaca di Excel (Wrap Text)

        // Mapping Label Status
        $statusLabels = [
            'pending'    => 'Menunggu',
            'paid'       => 'Dibayar',
            'processing' => 'Diproses',
            'completed'  => 'Selesai',
            'cancelled'  => 'Dibatalkan',
        ];

        // Mapping Label Tipe
        $orderTypeLabels = [
            'pickup'   => 'Pickup',
            'dine-in'  => 'Dine In',
        ];

        return [
            $order->id,
            $order->order_number,
            $order->user->name ?? 'User Terhapus',
            $order->user->email ?? '-',
            $orderTypeLabels[$order->order_type] ?? $order->order_type,
            $order->table_number ?? '-',
            number_format($order->total_amount, 0, ',', '.'),
            $statusLabels[$order->status] ?? $order->status,
            // Logika Pembayaran
            $order->payment?->payment_method === 'cash_on_pickup'
                ? 'Bayar di Kafe'
                : ($order->payment?->payment_method ?? 'Belum Bayar'),
            $order->created_at->format('d/m/Y H:i'),
            $itemDetails,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 25,
            'C' => 20,
            'D' => 30,
            'E' => 15,
            'F' => 12,
            'G' => 18,
            'H' => 15,
            'I' => 20,
            'J' => 20,
            'K' => 60, // Kolom detail item dibuat lebar
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Mengaktifkan Wrap Text untuk kolom K (Detail Item) agar newline bekerja
        $sheet->getStyle('K')->getAlignment()->setWrapText(true);

        // Perataan tengah untuk seluruh sheet
        $sheet->getStyle('A:J')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [
            // Baris 1 (Header)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2D6A4F'], // Hijau Tua Kopitiam
                ],
            ],
            // Tambahkan border untuk seluruh data yang ada
            'A1:K' . ($sheet->getHighestRow()) => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}
