<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Product;
use App\Models\CarPackage;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookingController extends Controller
{

    /* config publik frontend untuk mode booking apa */
    public function config()
    {
        return response()->json([
            'mode' => config('booking.mode'),
            'calendar_enabled' => (bool) config('booking.calendar_enabled'),
            'payment_proof_enabled' => (bool) config('booking.payment_proof_enabled'),
            'whatsapp_number' => $this->sanitizePhoneNumber(Setting::get('contact_phone')) ?? config('booking.whatsapp_number'),
        ]);
    }

    /**
     * Get data booking by ID
     */
    public function show(Booking $booking)
    {
        return response()->json(new BookingResource($booking->load('unit.product', 'payments')));
    }

    /**
     * Submit booking dari frontend
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'package_id' => 'nullable|exists:packages,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'with_driver' => 'boolean',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $product = Product::findOrFail($data['product_id']);
        $mode = config('booking.mode');

        // mode whatsapp only hanya generate link untuk redirect tanpa submit ke db
        if ($mode === 'whatsapp_only') {
            return response()->json([
                'success' => true,
                'saved' => false,
                'whatsapp_link' => $this->buildWhatsappLink($product, $data),
            ]);
        }

        $shouldSave = $mode === 'calendar_booking'
            || ($mode === 'whatsapp_form' && config('booking.save_on_form_submit'));

        // mode whatsapp form cuma gerneate link ga submit ke db
        if (! $shouldSave) {
            return response()->json([
                'success' => true,
                'saved' => false,
                'whatsapp_link' => $this->buildWhatsappLink($product, $data),
            ]);
        }

        // simpan ke db butuh unit fisik yang kosong
        $unit = $this->findAvailableUnit($product->id, $data['start_date'], $data['end_date']);

        if (! $unit) {
            return response()->json([
                'success' => false,
                'message' => 'Mohon maaf, semua unit sudah penuh di tanggal yang dipilih. Silakan pilih tanggal lain.',
            ], 422);
        }

        [$packageLabel, $packagePrice, $package] = $this->resolvePackage($product->id, $data['package_id'] ?? null);

        
        $startDate = \Carbon\Carbon::parse($data['start_date']);
        $endDate = $this->calculateEndDate($startDate, $package);

        $withDriver = (bool) ($data['with_driver'] ?? false);
        $driverFee = 0;

        if ($withDriver) {
            $driverFee = $package?->effective_driver_fee ?? (int) Setting::get('driver_surcharge', 0);
        }

        $booking = Booking::create([
            'product_unit_id' => $unit->id,
            'start_date' => $data['start_date'],
            'end_date' => $endDate->toDateString(),
            'package_id' => $data['package_id'] ?? null,
            'package_label' => $packageLabel,
            'package_price' => $packagePrice,
            'with_driver' => $withDriver,
            'driver_surcharge_price' => $withDriver ? $driverFee : 0,
            'status' => 'pending',
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'notes' => $data['notes'] ?? null,
            'source' => 'form',
        ]);

        return response()->json([
            'success' => true,
            'saved' => true,
            'booking' => new BookingResource($booking->load('unit.product')),
            'whatsapp_link' => $this->buildWhatsappLink($product, $data),
        ]);
    }

    /* cancel booking dari customer pakai cancel token yang direturn pas booking berhasil dibuat */

    public function cancel(Request $request, Booking $booking)
    {
        $validator = Validator::make($request->all(), [
            'cancel_token' => 'required|string',
        ]);
 
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
 
        if (! $booking->cancel_token || ! hash_equals($booking->cancel_token, $request->cancel_token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid.',
            ], 403);
        }
 
        if (! $booking->isCancellableByCustomer()) {
            return response()->json([
                'success' => false,
                'message' => 'Booking ini tidak bisa dibatalkan otomatis (sudah lunas/dikonfirmasi/di-lock). Silakan hubungi kami langsung.',
            ], 422);
        }
 
        $booking->update(['status' => 'cancelled']);
 
        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan.',
        ]);
    }
 


    protected function calculateEndDate(\Carbon\Carbon $startDate, ?\App\Models\Package $package): \Carbon\Carbon
    {
        if (! $package) {
            return $startDate->copy();
        }

        return match ($package->duration_unit) {
            'hour' => $startDate->copy(), // 12 Jam & 24 Jam -- tetap blok 1 hari kalender
            'day' => $startDate->copy()->addDays(max(0, $package->duration_value - 1)),
            default => $startDate->copy(),
        };
    }

    /* cari unit yang aktif dan kosong termasuk blokir servis di tanggal filter sort order dari terkecil */
    protected function findAvailableUnit(int $productId, string $start, string $end): ?ProductUnit
    {
        return ProductUnit::where('product_id', $productId)
            ->active()
            ->ordered()
            ->get()
            ->first(fn (ProductUnit $unit) => ! Booking::overlapping($unit->id, $start, $end)->exists());
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: ?\App\Models\Package}
     */
    protected function resolvePackage(int $productId, ?int $packageId): array
    {
        if (! $packageId) {
            return [null, null, null];
        }

        $productPackage = CarPackage::with('package')
            ->where('product_id', $productId)
            ->where('package_id', $packageId)
            ->first();

        if (! $productPackage) {
            return [null, null, null];
        }

        return [$productPackage->package->name, $productPackage->price, $productPackage->package];
    }

    /**
     * nomor telepon jadi format tanpa simbol,
     */
    protected function sanitizePhoneNumber(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $number);

        // Nomor lokal diawali 0 ganti jadi kode negara 62
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return $digits ?: null;
    }

    protected function buildWhatsappLink(Product $product, array $data, ?string $packageLabel = null): string
    {
        $number = $this->sanitizePhoneNumber(Setting::get('contact_phone'));
        $siteName = Setting::get('site_name', 'Kami');
 
        $lines = [
            "Halo *{$siteName}*, saya ingin mengajukan reservasi mobil:",
            '',
            "Mobil: *{$product->name}*",
            "Tanggal: {$data['start_date']} s/d {$data['end_date']}",
        ];
 
        if ($packageLabel) {
            $lines[] = "📦 Paket: {$packageLabel}";
        }
 
        $lines[] = ! empty($data['with_driver'])
            ? 'Layanan Sopir: Ya'
            : 'Layanan: Lepas Kunci';
 
        $lines = array_merge($lines, [
            '',
            "Nama: {$data['customer_name']}",
            "No HP: {$data['customer_phone']}",
        ]);
 
        if (! empty($data['notes'])) {
            $lines[] = "Catatan: {$data['notes']}";
        }
 
        $lines[] = '';
        $lines[] = 'Mohon informasi ketersediaan dan konfirmasinya. Terima kasih.';
 
        $message = collect($lines)->implode("\n");
 
        return $number
            ? "https://wa.me/{$number}?text=" . urlencode($message)
            : '';
    }

    /**
     * Bikin booking + link pembayaran online.
     * semua lewat interface PaymentGateway,
     * tinggal ganti payment_gateway=midtrans kalau ganti gateway
     */
    public function payNow(Request $request, PaymentGatewayManager $gatewayManager)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'package_id' => 'required|exists:packages,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'with_driver' => 'boolean',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'required|email|max:255',
            'notes' => 'nullable|string|max:1000',
            'payment_gateway' => 'nullable|string|max:50', // doku, midtrans atau lainnya
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
    
        $data = $validator->validated();
        $product = Product::findOrFail($data['product_id']);
        $selectedGateway = $data['payment_gateway'] ?? config('services.payment_gateways.default', 'doku');
    
        // validasi unit fisik kosong di rentang tanggal
        $unit = $this->findAvailableUnit($product->id, $data['start_date'], $data['end_date']);
    
        if (! $unit) {
            return response()->json([
                'success' => false,
                'message' => 'Mohon maaf, semua unit sudah penuh di tanggal yang dipilih. Silakan pilih tanggal lain.',
            ], 422);
        }
    
        [$packageLabel, $packagePrice, $package] = $this->resolvePackage($product->id, $data['package_id'] ?? null);
    
        $startDate = \Carbon\Carbon::parse($data['start_date']);
        $endDate = $this->calculateEndDate($startDate, $package);
    
        $withDriver = (bool) ($data['with_driver'] ?? false);
        $driverFee = 0;
    
        if ($withDriver) {
            $driverFee = $package?->effective_driver_fee ?? (int) Setting::get('driver_surcharge', 0);
        }
    
        // Sesuai getTotalPriceAttribute(): package_price + driver_surcharge_price 
        $grossAmount = (int) $packagePrice + ($withDriver ? $driverFee : 0);
        $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        $paymentDueMinutes = 60;
    
        // simpan booking dulu, kalau gateway gagal nanti di-cancel lagi di bawah
        $booking = Booking::create([
            'product_unit_id' => $unit->id,
            'start_date' => $data['start_date'],
            'end_date' => $endDate->toDateString(),
            'package_id' => $data['package_id'] ?? null,
            'package_label' => $packageLabel,
            'package_price' => $packagePrice,
            'with_driver' => $withDriver,
            'driver_surcharge_price' => $withDriver ? $driverFee : 0,
            'status' => 'pending',
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'notes' => $data['notes'] ?? null,
            'source' => 'payment_gateway',
            'payment_gateway' => $selectedGateway,
            'gateway_order_id' => $invoiceNumber,
            'gross_amount' => $grossAmount,
            'expired_at' => now()->addMinutes($paymentDueMinutes),
        ]);
    
        $gateway = $gatewayManager->resolve($selectedGateway);

        // apping ke format tiap gateway
        // dilakukan di dalam masing-masing *CheckoutService
        $result = $gateway->createPayment(
            order: [
                'amount' => $grossAmount,
                'invoice_number' => $invoiceNumber,
                'payment_due_date' => $paymentDueMinutes,
                'line_items' => [[
                    'name' => $product->name . ($packageLabel ? " ({$packageLabel})" : ''),
                    'price' => $grossAmount,
                    'quantity' => 1,
                ]],
            ],
            customer: [
                'name' => $data['customer_name'],
                'email' => $data['customer_email'],
                'phone' => $data['customer_phone'],
            ],
            callbackUrl: rtrim(config('app.frontend_url'), '/') . '/reservasi/selesai?booking=' . $booking->id,
        );
    
        if (! $result['success']) {
            // gagal generate link cancel booking biar kosong unit nya ga nyangkut
            $booking->update(['status' => 'cancelled']);
    
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Gagal membuat link pembayaran. Coba lagi.',
            ], 502);
        }
    
        $booking->update([
            'payment_redirect_url' => $result['payment_url'],
            'gateway_status' => 'PENDING',
        ]);
    
        return response()->json([
            'success' => true,
            'saved' => true,
            'booking' => new BookingResource($booking->load('unit.product')),
            'payment_url' => $result['payment_url'],
        ]);
    }

}