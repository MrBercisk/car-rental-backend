<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface PaymentGateway
{
    public function getName(): string;

    public function createPayment(array $order, array $customer, ?string $callbackUrl = null): array;

    public function verifyNotificationSignature(Request $request): bool;

    public function handleNotification(Request $request): array;
}
