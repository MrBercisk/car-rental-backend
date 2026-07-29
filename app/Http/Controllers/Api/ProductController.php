<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Booking;
use App\Models\Product;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'available_from' => 'nullable|date',
            'available_to' => 'nullable|date|after_or_equal:available_from',
            'fuel_type' => 'nullable|string|in:bensin,diesel,listrik,hybrid',
            'seat_capacity' => 'nullable|integer|min:1',
        ]);

        $query = Product::query()
            ->with(['category', 'packages.package'])
            ->where('is_available', true);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('transmission')) {
            $query->where('transmission', $request->transmission);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->search);
            $query->whereRaw('name LIKE ? ESCAPE ?', ['%' . $search . '%', '\\']);
        }

        // filter cari mobil yang kosong di rental tanggal ini
        if ($request->filled('available_from') && $request->filled('available_to')) {
            $from = $request->available_from;
            $to = $request->available_to;

            $query->whereHas('units', function ($unitQuery) use ($from, $to) {
                $unitQuery->where('condition_status', 'active')
                    ->whereDoesntHave('bookings', function ($bookingQuery) use ($from, $to) {
                        $bookingQuery->whereIn('status', ['pending', 'dp', 'lunas', 'confirmed'])
                            ->where('start_date', '<=', $to)
                            ->where('end_date', '>=', $from);
                    });
            });
        }
        // Filter rentang harga
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $min = (float) $request->get('min_price', 0);
            $max = (float) $request->get('max_price', PHP_INT_MAX);
 
            $query->whereHas('packages', function ($packageQuery) use ($min, $max) {
                $packageQuery->whereBetween('price', [$min, $max]);
            });
        }

        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        if ($request->filled('seat_capacity')) {
            $query->where('seat_capacity', '>=', (int) $request->seat_capacity);
        }

        $perPage = (int) $request->get('per_page', 12);
        $perPage = max(1, min($perPage, 50));

        $products = $query->orderBy('sort_order')->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function show(string $slug)
    {
        $product = Product::with(['category', 'packages.package'])
            ->where('slug', $slug)
            ->where('is_available', true)
            ->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * Ketersediaan produk dalam rentang tanggal. Mengembalikan daftar
     * tanggal yang TIDAK tersedia (semua unit aktif penuh/bentrok di
     * tanggal itu) -- frontend cukup disable tanggal-tanggal ini di kalender.
     *
     * GET /api/products/{slug}/availability?from=2026-08-01&to=2026-09-30
     */
    public function availability(string $slug, Request $request)
    {
        $product = Product::where('slug', $slug)
            ->where('is_available', true)
            ->firstOrFail();

        $from = $request->filled('from')
            ? \Carbon\Carbon::parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->filled('to')
            ? \Carbon\Carbon::parse($request->to)->endOfDay()
            : now()->addMonths(2)->endOfDay();

        $unitIds = $product->units()->active()->pluck('id');

        // Gak ada unit aktif sama sekali semua tanggal dianggap gak tersedia
        if ($unitIds->isEmpty()) {
            $allDates = collect(CarbonPeriod::create($from, $to))
                ->map(fn ($date) => \Carbon\Carbon::parse((string) $date)->toDateString())
                ->values();

            return response()->json([
                'product_id' => $product->id,
                'unavailable_dates' => $allDates,
            ]);
        }

        $bookings = Booking::whereIn('product_unit_id', $unitIds)
            ->whereIn('status', ['pending', 'dp', 'lunas', 'confirmed'])
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get(['product_unit_id', 'start_date', 'end_date']);

        $totalUnits = $unitIds->count();
        $unavailableDates = [];

        foreach (CarbonPeriod::create($from, $to) as $rawDate) {
            $date = \Carbon\Carbon::parse((string) $rawDate);

            $bookedUnitCount = $bookings
                ->filter(fn ($booking) => $booking->start_date->lte($date) && $booking->end_date->gte($date))
                ->pluck('product_unit_id')
                ->unique()
                ->count();

            if ($bookedUnitCount >= $totalUnits) {
                $unavailableDates[] = $date->toDateString();
            }
        }

        return response()->json([
            'product_id' => $product->id,
            'unavailable_dates' => $unavailableDates,
        ]);
    }
}