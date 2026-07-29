<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapUnitExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected Builder $query,
        protected string $dari,
        protected string $sampai,
    ) {
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Unit', 'Mobil', 'Status Saat Ini', 'Jumlah Sewa',
            'Hari Disewa', 'Utilisasi (Total Hari Disewa ÷ Total Hari dalam Periode × 100%)', 'Pendapatan',
        ];
    }

    public function map($row): array
    {
        return [
            $row->license_plate,
            $row->product?->name,
            match ($row->status_saat_ini) {
                'disewa' => 'Disewa',
                'maintenance' => 'Maintenance',
                default => 'Tersedia',
            },
            $row->total_bookings,
            $row->total_hari_disewa,
            $row->utilisasi_persen,
            $row->total_pendapatan,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}