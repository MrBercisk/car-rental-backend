<?php

use App\Contracts\PaymentGateway;
use App\Services\PaymentGatewayManager;

uses(Tests\TestCase::class);

it('resolves the configured payment gateway', function () {
    config()->set('services.payment_gateways.default', 'doku');
    config()->set('services.payment_gateways.doku.driver', 'doku');

    $manager = app(PaymentGatewayManager::class);
    $gateway = $manager->resolve();

    expect($gateway)->toBeInstanceOf(PaymentGateway::class)
        ->and($gateway->getName())->toBe('doku');
});
