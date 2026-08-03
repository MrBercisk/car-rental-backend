<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_menyimpan_delivery_info_dan_menghitung_delivery_fee_otomatis(): void
    {
        config()->set('booking.mode', 'calendar_booking');
        config()->set('booking.save_on_form_submit', true);

        $product = Product::factory()->create();
        ProductUnit::create([
            'product_id' => $product->id,
            'license_plate' => 'B 1234 ABC',
            'condition_status' => 'active',
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/v1/bookings', [
            'product_id' => $product->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'delivery_address' => 'Bandara YIA',
            'delivery_distance_km' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking.delivery_address', 'Bandara YIA')
            ->assertJsonPath('booking.delivery_distance_km', 25.0)
            ->assertJsonPath('booking.delivery_fee_price', 125000);

        $booking = Booking::latest('id')->first();

        $this->assertNotNull($booking);
        $this->assertSame('Bandara YIA', $booking->delivery_address);
        $this->assertSame(25.0, (float) $booking->delivery_distance_km);
        $this->assertSame(125000, $booking->delivery_fee_price);
        $this->assertSame(125000, $booking->total_price);
    }
}
