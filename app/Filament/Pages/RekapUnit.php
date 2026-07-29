<?php

namespace App\Filament\Pages;

use App\Exports\RekapUnitExport;
use App\Models\Product;
use App\Models\ProductUnit as Unit;
use Carbon\Carbon;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\ActionGroup as ActionsActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class RekapUnit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Penyewaan';

    protected static ?string $navigationLabel = 'Rekap Unit/Armada';

    protected static ?string $title = 'Rekap Unit / Armada';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.rekap-unit';

    /**
     * Ambil rentang periode dari state filter tabel, default: bulan berjalan.
     *
     * @return array{0: string, 1: string} [dari, sampai]
     */
    protected function getPeriode(): array
    {
        $dari = $this->tableFilters['periode']['dari']
            ?? now()->startOfMonth()->toDateString();

        $sampai = $this->tableFilters['periode']['sampai']
            ?? now()->endOfMonth()->toDateString();

        return [$dari, $sampai];
    }

    public function table(Table $table): Table
    {
        [$dari, $sampai] = $this->getPeriode();

        $periodeHari = Carbon::parse($dari)->diffInDays(Carbon::parse($sampai)) + 1;
        $periodeHari = max($periodeHari, 1);

        // Ekspresi total_price yang sama seperti di RekapRental, tapi dijalankan
        // sebagai subquery ter-korelasi ke bookings per unit.
        $bookingOverlap = 'b.status != \'cancelled\' AND b.start_date <= ? AND b.end_date >= ?';

        $totalBookingsExpr = "(SELECT COUNT(*) FROM bookings b WHERE b.product_unit_id = product_units.id AND {$bookingOverlap})";

        $totalHariExpr = "(SELECT COALESCE(SUM(DATEDIFF(LEAST(b.end_date, ?), GREATEST(b.start_date, ?)) + 1), 0)"
            . " FROM bookings b WHERE b.product_unit_id = product_units.id AND {$bookingOverlap})";

        $totalRevenueExpr = "(SELECT COALESCE(SUM("
            . "COALESCE(b.package_price, 0)"
            . " + (CASE WHEN b.with_driver = 1 THEN COALESCE(b.driver_surcharge_price, 0) ELSE 0 END)"
            . " + COALESCE(b.delivery_fee_price, 0)"
            . "), 0) FROM bookings b WHERE b.product_unit_id = product_units.id AND {$bookingOverlap})";

        $currentStatusExpr = "(SELECT CASE WHEN b.source = 'maintenance' THEN 'maintenance' ELSE 'disewa' END"
            . " FROM bookings b WHERE b.product_unit_id = product_units.id AND b.status != 'cancelled'"
            . " AND CURDATE() BETWEEN b.start_date AND b.end_date"
            . " ORDER BY b.start_date DESC LIMIT 1)";

        return $table
            ->query(
                Unit::query()
                    ->select('product_units.*')
                    ->selectRaw("{$totalBookingsExpr} as total_bookings", [$sampai, $dari])
                    ->selectRaw("{$totalHariExpr} as total_hari_disewa", [$sampai, $dari, $sampai, $dari])
                    ->selectRaw(
                        "ROUND({$totalHariExpr} / ? * 100, 1) as utilisasi_persen",
                        [$sampai, $dari, $sampai, $dari, $periodeHari]
                    )
                    ->selectRaw("{$totalRevenueExpr} as total_pendapatan", [$sampai, $dari])
                    ->selectRaw("COALESCE({$currentStatusExpr}, 'tersedia') as status_saat_ini")
                    ->with('product')
            )
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Unit')
                    ->description(fn (Unit $record) => $record->product?->name)
                    ->searchable(),

                TextColumn::make('status_saat_ini')
                    ->label('Status Saat Ini')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'disewa' => 'info',
                        'maintenance' => 'danger',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'disewa' => 'Disewa',
                        'maintenance' => 'Maintenance',
                        default => 'Tersedia',
                    }),

                TextColumn::make('total_bookings')
                    ->label('Jumlah Sewa')
                    ->sortable()
                    ->alignCenter()
                    ->summarize(Sum::make()->label('Total')),

                TextColumn::make('total_hari_disewa')
                    ->label('Hari Disewa')
                    ->sortable()
                    ->alignCenter()
                    ->summarize(Sum::make()->label('Total')),

                TextColumn::make('utilisasi_persen')
                    ->label('Utilisasi')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('total_pendapatan')
                    ->label('Pendapatan')
                    ->money('IDR')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('IDR')
                    ),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')
                            ->label('Mulai Dari')
                            ->native(false)
                            ->default(now()->startOfMonth()->toDateString()),
                        DatePicker::make('sampai')
                            ->label('Sampai')
                            ->native(false)
                            ->default(now()->endOfMonth()->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query)
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari'] ?? null) {
                            $indicators[] = 'Dari ' . Carbon::parse($data['dari'])->format('d M Y');
                        }
                        if ($data['sampai'] ?? null) {
                            $indicators[] = 'Sampai ' . Carbon::parse($data['sampai'])->format('d M Y');
                        }

                        return $indicators;
                    }),

                Filter::make('product_id')
                    ->schema([
                        Select::make('product_id')
                            ->label('Mobil')
                            ->options(fn () => Product::pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['product_id'] ?? null,
                        fn ($q, $productId) => $q->where('product_id', $productId)
                    )),
            ])
            ->headerActions([
                ActionsActionGroup::make([
                ActionsAction::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-document-text')
                    ->action(fn () => $this->exportCsv()),

                ActionsAction::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => $this->exportExcel()),
            ])
             ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('utilisasi_persen', 'desc');
    }

    protected function exportExcel()
    {
        [$dari, $sampai] = $this->getPeriode();

        $filename = 'rekap-unit-' . now()->format('Y-m-d-His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new RekapUnitExport($this->getFilteredTableQuery(), $dari, $sampai),
            $filename
        );
    }

    protected function exportCsv()
    {
        $rows = $this->getFilteredTableQuery()->get();

        $filename = 'rekap-unit-' . now()->format('Y-m-d-His') . '.csv';

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Unit', 'Mobil', 'Status Saat Ini', 'Jumlah Sewa',
                'Hari Disewa', 'Utilisasi (%)', 'Pendapatan',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->license_plate,
                    $row->product?->name,
                    $row->status_saat_ini,
                    $row->total_bookings,
                    $row->total_hari_disewa,
                    $row->utilisasi_persen,
                    $row->total_pendapatan,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}