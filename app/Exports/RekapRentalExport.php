<?php

namespace App\Exports;

use App\Models\Booking;
use App\Support\TextSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapRentalExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected Builder $query)
    {
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Pelanggan', 'No. HP', 'Unit', 'Mobil', 'Paket',
            'Mulai', 'Selesai', 'Status', 'Sumber',
            'Total Tagihan', 'Terbayar', 'Sisa Tagihan', 'Dibuat',
        ];
    }

    /**
     * @param  Booking  $row
     */
    public function map($row): array
    {
        return [
            TextSanitizer::clean($row->customer_name),
            TextSanitizer::clean($row->customer_phone),
            TextSanitizer::clean($row->unit?->license_plate),
            TextSanitizer::clean($row->unit?->product?->name),
            TextSanitizer::clean($row->package_label),
            optional($row->start_date)->format('Y-m-d'),
            optional($row->end_date)->format('Y-m-d'),
            $row->status,
            $row->source,
            $row->total_price,
            $row->amount_paid,
            max(0, $row->total_price - $row->amount_paid),
            optional($row->created_at)->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}