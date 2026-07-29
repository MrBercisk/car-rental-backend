<?php

namespace App\Filament\Pages;

use App\Exports\RekapRentalExport;
use App\Models\Booking;
use App\Models\Product;
use App\Support\TextSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\ActionGroup as ActionsActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

class RekapRental extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Penyewaan';

    protected static ?string $navigationLabel = 'Rekap Rental';

    protected static ?string $title = 'Rekap Rental';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.rekap-rental';

    public function table(Table $table): Table
    {
        // Ekspresi SQL yang persis meniru logika accessor getTotalPriceAttribute() di model Booking:
        // total_price = package_price + (driver_surcharge_price kalau with_driver aktif) + delivery_fee_price
        $totalPriceExpr = '(COALESCE(bookings.package_price, 0)'
            . ' + (CASE WHEN bookings.with_driver = 1 THEN COALESCE(bookings.driver_surcharge_price, 0) ELSE 0 END)'
            . ' + COALESCE(bookings.delivery_fee_price, 0))';

        return $table
            ->query(
                Booking::query()
                    ->select('bookings.*')
                    ->selectRaw("{$totalPriceExpr} as total_price")
                    ->selectRaw("GREATEST({$totalPriceExpr} - COALESCE(bookings.amount_paid, 0), 0) as outstanding")
                    ->with(['unit.product', 'package'])
                    ->withoutGlobalScopes()
            )
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->description(fn (Booking $record) => $record->customer_phone),

                TextColumn::make('unit.license_plate')
                    ->label('Unit')
                    ->description(fn (Booking $record) => $record->unit?->product?->name)
                    ->searchable(),

                TextColumn::make('package_label')
                    ->label('Paket')
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'dp' => 'warning',
                        'lunas' => 'success',
                        'confirmed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge(),

                TextColumn::make('total_price')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('IDR')
                    ),

                TextColumn::make('amount_paid')
                    ->label('Terbayar')
                    ->money('IDR')
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('IDR')
                    ),

                TextColumn::make('outstanding')
                    ->label('Sisa Tagihan')
                    ->money('IDR')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('IDR')
                    ),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Mulai Dari')->native(false),
                        DatePicker::make('sampai')->label('Sampai')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['sampai'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date));
                    })
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

                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'pending' => 'Pending',
                        'dp' => 'DP',
                        'lunas' => 'Lunas',
                        'confirmed' => 'Dikonfirmasi',
                        'cancelled' => 'Dibatalkan',
                    ]),

                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'form' => 'Form Website',
                        'admin' => 'Input Admin',
                        'maintenance' => 'Blokir Servis / Maintenance',
                    ]),

                Filter::make('product_id')
                    ->schema([
                        Select::make('product_id')
                            ->label('Mobil')
                            ->options(fn () => Product::pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['product_id'] ?? null,
                            fn ($q, $productId) => $q->whereHas('unit', fn ($u) => $u->where('product_id', $productId))
                        );
                    }),

                Filter::make('aktif_sekarang')
                    ->label('Sedang Aktif Hari Ini')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->whereIn('status', ['pending', 'dp', 'lunas', 'confirmed'])
                        ->whereDate('start_date', '<=', now())
                        ->whereDate('end_date', '>=', now())
                    ),
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

                    // ActionsAction::make('export_pdf')
                    //     ->label('Export PDF')
                    //     ->icon('heroicon-o-document')
                    //     ->action(fn () => $this->exportPdf()),
                ])
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('start_date', 'desc');
    }

    protected function exportExcel()
    {
        $query = $this->getFilteredTableQuery()->with(['unit.product']);

        $filename = 'rekap-rental-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new RekapRentalExport($query), $filename);
    }

    protected function exportPdf()
    {
        $records = $this->getFilteredTableQuery()
            ->with(['unit.product'])
            ->get();

        $clean = fn (?string $value): ?string => TextSanitizer::clean($value);

        $rows = $records->map(function (Booking $row) use ($clean) {
            $unitLabel = trim(($row->unit?->license_plate ?? '') . ' - ' . ($row->unit?->product?->name ?? ''), ' -');

            return [
                'customer_name' => $clean($row->customer_name) ?: '-',
                'unit_label' => $clean($unitLabel) ?: '-',
                'package_label' => $clean($row->package_label) ?: '-',
                'start_date' => optional($row->start_date)->format('d M Y'),
                'end_date' => optional($row->end_date)->format('d M Y'),
                'status' => $clean($row->status),
                'total_price' => $row->total_price,
                'amount_paid' => $row->amount_paid,
                'outstanding' => max(0, $row->total_price - $row->amount_paid),
            ];
        });

        $filename = 'rekap-rental-' . now()->format('Y-m-d-His') . '.pdf';

        $pdf = Pdf::loadView('exports.rekap-rental-pdf', [
            'rows' => $rows,
            'totalPrice' => $rows->sum('total_price'),
            'totalPaid' => $rows->sum('amount_paid'),
            'totalOutstanding' => $rows->sum('outstanding'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    protected function exportCsv()
    {
        $rows = $this->getFilteredTableQuery()
            ->with(['unit.product'])
            ->get();

        $filename = 'rekap-rental-' . now()->format('Y-m-d-His') . '.csv';

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM biar Excel baca UTF-8 dengan benar
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Pelanggan', 'No. HP', 'Unit', 'Mobil', 'Paket',
                'Mulai', 'Selesai', 'Status', 'Sumber',
                'Total Tagihan', 'Terbayar', 'Sisa Tagihan', 'Dibuat',
            ]);

            foreach ($rows as $row) {
                $outstanding = max(0, $row->total_price - $row->amount_paid);

                fputcsv($handle, [
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
                    $outstanding,
                    optional($row->created_at)->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}