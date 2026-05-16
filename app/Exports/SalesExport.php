<?php

namespace App\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SalesExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    protected $startDate;
    protected $endDate;
    protected int $rowNumber = 0;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate   = Carbon::parse($endDate)->endOfDay();
    }

    // Nama sheet — menggantikan nama default "Sheet1"
    public function title(): string
    {
        return 'Detail Transaksi';
    }

    public function collection()
    {
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
            'No',
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
        $this->rowNumber++;

        $itemDetails = $order->items->map(function ($item) {
            return "{$item->quantity}x {$item->product_name} (@Rp "
                . number_format($item->price, 0, ',', '.') . ")";
        })->implode("\n");

        $statusLabels = [
            'pending'    => 'Menunggu',
            'paid'       => 'Dibayar',
            'processing' => 'Diproses',
            'completed'  => 'Selesai',
            'cancelled'  => 'Dibatalkan',
        ];

        $orderTypeLabels = [
            'pickup'  => 'Pickup',
            'dine-in' => 'Dine In',
        ];

        return [
            $this->rowNumber,                    // No — integer murni
            (int) $order->id,                    // ID Pesanan — integer murni
            $order->order_number,
            $order->user->name  ?? 'User Terhapus',
            $order->user->email ?? '-',
            $orderTypeLabels[$order->order_type] ?? $order->order_type,
            $order->table_number ?? '-',
            number_format($order->total_amount, 0, ',', '.'),
            $statusLabels[$order->status] ?? $order->status,
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
            'A' => 6,   // No
            'B' => 12,  // ID Pesanan
            'C' => 25,  // Nomor Pesanan
            'D' => 20,  // Pelanggan
            'E' => 30,  // Email Pelanggan
            'F' => 15,  // Tipe Pesanan
            'G' => 12,  // Nomor Meja
            'H' => 18,  // Total (Rp)
            'I' => 15,  // Status
            'J' => 20,  // Metode Pembayaran
            'K' => 20,  // Tanggal Pesanan
            'L' => 60,  // Detail Item
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // FIX: Format kolom No & ID Pesanan sebagai integer — mencegah "1.00", "90.00"
        $sheet->getStyle('A2:A' . $lastRow)
              ->getNumberFormat()
              ->setFormatCode(NumberFormat::FORMAT_NUMBER);

        $sheet->getStyle('B2:B' . $lastRow)
              ->getNumberFormat()
              ->setFormatCode(NumberFormat::FORMAT_NUMBER);

        // Wrap text untuk kolom Detail Item agar newline (\n) tampil rapi
        $sheet->getStyle('L')->getAlignment()->setWrapText(true);

        // Perataan vertikal tengah untuk semua kolom kecuali Detail Item
        $sheet->getStyle('A:K')
              ->getAlignment()
              ->setVertical(Alignment::VERTICAL_CENTER);

        return [
            // Styling baris header (baris 1)
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2D6A4F'],
                ],
            ],
            // Border tipis untuk seluruh data
            'A1:L' . $lastRow => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}
