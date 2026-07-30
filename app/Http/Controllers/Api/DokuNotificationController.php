<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook DOKU Kklo ada gateway lain, bikin controller sendiri
 * (URL & cara verifikasi signature beda-beda tiap gateway)
 */
class DokuNotificationController extends Controller
{
    public function handle(Request $request, PaymentGatewayManager $gatewayManager)
    {
        // hardcode 'doku', jangan resolve() tanpa argumen -- biar gak ikut default config
        $gateway = $gatewayManager->resolve('doku');
        $rawBody = $request->getContent();

        // Skip signature verification di env development buat testing postman
        $skipSignatureCheck = ! config('services.doku.is_production');

        if (! $skipSignatureCheck) {
            $isValid = $gateway->verifyNotificationSignature($request);

            if (! $isValid) {
                Log::warning('DOKU notification: signature tidak valid', ['headers' => $request->headers->all()]);

                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } else {
            Log::info('DOKU notification: signature verification dilewati (sandbox mode)');
        }

        // handleNotification() samain payload gateway apapun jadi format ini
        $notification = $gateway->handleNotification($request);
        $payload = $notification['payload'] ?? [];
        $invoiceNumber = $notification['invoice_number'] ?? null;
        $transactionId = $notification['transaction_id'] ?? null;
        $transactionStatus = strtoupper((string) ($notification['transaction_status'] ?? ''));
        $paymentMethod = $notification['payment_method'] ?? null;

        Log::info('Payment notification diterima', ['gateway' => $gateway->getName(), 'payload' => $payload]);

        if (! $invoiceNumber) {
            return response()->json(['message' => 'OK']);
        }

        // cocokkan invoice + nama gateway, bukan invoice doang
        $booking = Booking::where('gateway_order_id', $invoiceNumber)
            ->where('payment_gateway', $gateway->getName())
            ->first();

        if (! $booking) {
            Log::warning('Payment notification: booking tidak ditemukan', ['gateway' => $gateway->getName(), 'invoice_number' => $invoiceNumber]);

            return response()->json(['message' => 'OK']);
        }

        // biar webhook yang dikirim ulang gak diproses dobel
        if ($booking->gateway_transaction_id === $transactionId && $booking->gateway_status === $transactionStatus) {
            return response()->json(['message' => 'OK']);
        }

        // status ini generik kalau gateway lain istilahnya beda, normalisasi di handleNotification()-nya, bukan di sini
        $isSuccess = in_array($transactionStatus, ['SUCCESS', 'SETTLEMENT', 'PAID']);
        $isFailedOrExpired = in_array($transactionStatus, ['FAILED', 'EXPIRED', 'CANCELLED', 'VOID']);

        // Snapshot status gateway di booking (bukan status booking utama)
        $booking->update([
            'gateway_transaction_id' => $transactionId,
            'gateway_status' => $transactionStatus,
            'gateway_payment_method' => $paymentMethod,
            'gateway_payload' => $payload,
        ]);

        if ($isSuccess && ! $booking->isLocked() && $booking->status !== 'cancelled') {
            $alreadyRecorded = $transactionId
                && $booking->payments()->where('gateway_transaction_id', $transactionId)->exists();

            if (! $alreadyRecorded) {
                $booking->payments()->create([
                    'amount' => $booking->gross_amount ?? $booking->total_price,
                    'type' => 'pelunasan',
                    'paid_at' => now(),
                    'method' => $paymentMethod,
                    'note' => 'Pembayaran otomatis via ' . ucfirst($gateway->getName()) . ' Checkout',
                    'gateway_transaction_id' => $transactionId,
                ]);
            }

            // Kalau payment_status_enabled mati (jadi recalculateAmountPaid tidak otomatis ubah status),
            // pastikan tetap jadi confirmed karena pelunasan penuh gateway bukan dp
            $booking->refresh();
            if (! in_array($booking->status, ['lunas', 'confirmed', 'cancelled'])) {
                $booking->update(['status' => 'confirmed', 'paid_at' => now()]);
            }
        } elseif ($isFailedOrExpired && $booking->status === 'pending' && ! $booking->isLocked()) {
            // Pembayaran gagal/kedaluwarsa -> bebaskan lagi unit yang sempat di-block
            $booking->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'OK']);
    }
}