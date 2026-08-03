<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wrapper DOKU Checkout API.
 * Docs: https://developers.doku.com/accept-payments/doku-checkout
 *
 * Flow:
 * 1. createPayment() -> POST /checkout/v1/payment -> dapat payment_url
 * 2. Redirect customer ke payment_url
 * 3. DOKU kirim webhook ke Notification URL -> verifikasi pakai verifyNotificationSignature()
 */
class DokuCheckoutService implements PaymentGateway
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

    public function getName(): string
    {
        return 'doku';
    }

    /**
     * Bikin payment link DOKU Checkout.
     * payment_method_types dikunci ke VIRTUAL_ACCOUNT_BCA saja,
     * jadi customer cuma lihat opsi BCA VA di halaman checkout.
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
                'payment_method_types' => ['VIRTUAL_ACCOUNT_BCA'], // batasi hanya BCA VA
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

    // Cek signature webhook dari DOKU biar gak dipalsuin orang lain
    public function verifyNotificationSignature(Request $request): bool
    {
        return $this->verifySignature(
            clientId: $request->header('Client-Id', ''),
            requestId: $request->header('Request-Id', ''),
            timestamp: $request->header('Request-Timestamp', ''),
            requestTarget: '/api/v1/doku/notification',
            rawBody: $request->getContent(),
            signatureHeader: $request->header('Signature', ''),
        );
    }

    // Parse payload webhook jadi array yang gampang dipakai
    public function handleNotification(Request $request): array
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true) ?? [];
        $invoiceNumber = data_get($payload, 'order.invoice_number');
        $transactionId = data_get($payload, 'transaction.id') ?? data_get($payload, 'transaction.original_request_id');
        $transactionStatus = strtoupper((string) data_get($payload, 'transaction.status'));
        $paymentMethod = data_get($payload, 'channel.id') ?? data_get($payload, 'payment.payment_method_types.0');

        return [
            'invoice_number' => $invoiceNumber,
            'transaction_id' => $transactionId,
            'transaction_status' => $transactionStatus,
            'payment_method' => $paymentMethod,
            'payload' => $payload,
            'raw_body' => $rawBody,
        ];
    }

    // Bikin signature buat request ke DOKU (dipakai createPayment)
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

    // Verifikasi signature webhook masuk, bandingin sama signature yang kita hitung sendiri
    protected function verifySignature(
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