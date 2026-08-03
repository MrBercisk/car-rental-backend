<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Str;

/**
 * Factory buat pilih payment gateway.
 * Nambah gateway baru buat class nya, implement PaymentGateway, tambah di match()
 */
class PaymentGatewayManager
{
    public function resolve(?string $name = null): PaymentGateway
    {
        $driver = $name ?? config('services.payment_gateways.default', 'doku');

        // bisa override class lewat config
        $config = config('services.payment_gateways.' . $driver, []);
        $className = data_get($config, 'class');

        if (! $className || ! class_exists($className)) {
            $className = match ($driver) {
                'doku' => DokuCheckoutService::class,
                // 'midtrans' => MidtransCheckoutService::class,
                default => throw new \InvalidArgumentException("Unsupported payment gateway: {$driver}"),
            };
        }

        $gateway = app($className);

        if (! $gateway instanceof PaymentGateway) {
            throw new \InvalidArgumentException("Payment gateway {$className} must implement App\\Contracts\\PaymentGateway");
        }

        return $gateway;
    }
}