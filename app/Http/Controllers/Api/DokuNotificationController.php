<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\DokuCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DokuNotificationController extends Controller
{
    public function handle(Request $request, DokuCheckoutService $doku)
    {
        $rawBody = $request->getContent();

        $isValid = $doku->verifyNotificationSignature(
            clientId: $request->header('Client-Id', ''),
            requestId: $request->header('Request-Id', ''),
            timestamp: $request->header('Request-Timestamp', ''),
            requestTarget: '/api/doku/notification',
            rawBody: $rawBody,
            signatureHeader: $request->header('Signature', ''),
        );

        if (! $isValid) {
            Log::warning('DOKU notification: signature tidak valid', ['headers' => $request->headers->all()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $invoiceNumber = data_get($payload, 'order.invoice_number');
        $transactionId = data_get($payload, 'transaction.id') ?? data_get($payload, 'transaction.original_request_id');
        $transactionStatus = strtoupper((string) data_get($payload, 'transaction.status')); // sesuaikan key ini dgn payload asli
        $paymentMethod = data_get($payload, 'channel.id') ?? data_get($payload, 'payment.payment_method_types.0');

        Log::info('DOKU notification diterima', ['payload' => $payload]);

        if (! $invoiceNumber) {
            return response()->json(['message' => 'OK']);
        }

        $booking = Booking::where('gateway_order_id', $invoiceNumber)
            ->where('payment_gateway', 'doku')
            ->first();

        if (! $booking) {
            Log::warning('DOKU notification: booking tidak ditemukan', ['invoice_number' => $invoiceNumber]);

            return response()->json(['message' => 'OK']);
        }

        if ($booking->gateway_transaction_id === $transactionId && $booking->gateway_status === $transactionStatus) {
            return response()->json(['message' => 'OK']);
        }

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
                    'note' => 'Pembayaran otomatis via Doku Checkout',
                    'gateway_transaction_id' => $transactionId,
                ]);
            }

            // Kalau payment_status_enabled mati (jadi recalculateAmountPaid tidak
            // otomatis ubah status), pastikan tetap ke-set 'confirmed' karena ini
            // pelunasan penuh via gateway -- bukan cicilan DP manual.
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