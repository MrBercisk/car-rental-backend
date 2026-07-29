<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wrapper untuk DOKU Checkout API.
 * Referensi resmi: https://developers.doku.com/accept-payments/doku-checkout
 *
 * Alur:
 * 1. createPayment() -> POST /checkout/v1/payment -> dapat payment_url,
 *    frontend redirect penuh customer ke payment_url tersebut.
 * 2. Setelah customer bayar, Doku kirim HTTP notification (webhook) ke
 *    Notification URL yang didaftarkan di Doku Dashboard -- verifikasi
 *    pakai verifyNotificationSignature().
 */
class DokuCheckoutService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.doku.base_url');
        $this->clientId = config('services.doku.client_id');
        $this->secretKey = config('services.doku.secret_key');
    }

    /**
     * Buat payment link di DOKU Checkout
     */
    public function createPayment(array $order, array $customer, ?string $callbackUrl = null): array
    {
        $requestTarget = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $requestTimestamp = gmdate('Y-m-d\TH:i:s\Z');

        $body = [
            'order' => array_filter([
                'amount' => $order['amount'],
                'invoice_number' => $order['invoice_number'],
                'currency' => 'IDR',
                'callback_url' => $callbackUrl,
                'line_items' => $order['line_items'] ?? null,
            ], fn ($v) => $v !== null),
            'payment' => [
                'payment_due_date' => $order['payment_due_date'] ?? 60, // menit
            ],
            'customer' => array_filter([
                'name' => $customer['name'],
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
                'address' => $customer['address'] ?? null,
                'country' => 'ID',
            ], fn ($v) => $v !== null),
        ];

        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        $signatureHeader = $this->buildRequestSignature($requestId, $requestTimestamp, $requestTarget, $jsonBody);

        $response = Http::withHeaders([
            'Client-Id' => $this->clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $requestTimestamp,
            'Signature' => $signatureHeader,
            'Content-Type' => 'application/json',
        ])->withBody($jsonBody, 'application/json')
            ->post($this->baseUrl . $requestTarget);

        if (! $response->successful()) {
            Log::warning('DOKU checkout gagal dibuat', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => data_get($response->json(), 'message.0', 'Gagal membuat link pembayaran Doku.'),
                'raw' => $response->json(),
            ];
        }

        $data = $response->json();

        return [
            'success' => true,
            'payment_url' => data_get($data, 'response.payment.url'),
            'invoice_number' => $order['invoice_number'],
            'raw' => $data,
        ];
    }

    /** header Signature untuk request ke Doku (dipakai createPayment). */
    protected function buildRequestSignature(string $requestId, string $requestTimestamp, string $requestTarget, string $jsonBody): string
    {
        $digest = base64_encode(hash('sha256', $jsonBody, true));

        $componentSignature = "Client-Id:{$this->clientId}\n"
            . "Request-Id:{$requestId}\n"
            . "Request-Timestamp:{$requestTimestamp}\n"
            . "Request-Target:{$requestTarget}\n"
            . "Digest:{$digest}";

        return 'HMACSHA256=' . base64_encode(
            hash_hmac('sha256', $componentSignature, $this->secretKey, true)
        );
    }

    /**
     * Verifikasi signature notifikasi/webhook yang dikirim Doku ke server
     * $requestTarget = path Notification URL yang didaftarkan di Doku Dashboard
     * (misal '/api/doku/notification').
     */
    public function verifyNotificationSignature(
        string $clientId,
        string $requestId,
        string $timestamp,
        string $requestTarget,
        string $rawBody,
        string $signatureHeader
    ): bool {
        $digest = base64_encode(hash('sha256', $rawBody, true));

        $componentSignature = "Client-Id:{$clientId}\n"
            . "Request-Id:{$requestId}\n"
            . "Request-Timestamp:{$timestamp}\n"
            . "Request-Target:{$requestTarget}\n"
            . "Digest:{$digest}";

        $expected = 'HMACSHA256=' . base64_encode(
            hash_hmac('sha256', $componentSignature, $this->secretKey, true)
        );

        return hash_equals($expected, $signatureHeader);
    }
}