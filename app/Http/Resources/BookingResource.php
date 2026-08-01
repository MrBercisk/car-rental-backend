<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'cancel_token' => $this->cancel_token,
            'invoice_number' => $this->gateway_order_id,
            'payment_gateway' => $this->payment_gateway,
            'gateway_status' => $this->gateway_status,
            'gross_amount' => (float) ($this->gross_amount ?? 0),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'package_label' => $this->package_label,
            'package_price' => (float) ($this->package_price ?? 0),
            'with_driver' => (bool) $this->with_driver,
            'driver_surcharge_price' => (float) ($this->driver_surcharge_price ?? 0),
            'delivery_address' => $this->delivery_address,
            'delivery_distance_km' => $this->delivery_distance_km !== null ? (float) $this->delivery_distance_km : null,
            'delivery_fee_price' => (float) ($this->delivery_fee_price ?? 0),
            'total_price' => (float) $this->total_price,
            'amount_paid' => (float) ($this->amount_paid ?? 0),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'product' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit?->product?->id,
                    'name' => $this->unit?->product?->name,
                    'slug' => $this->unit?->product?->slug,
                    'thumbnail' => $this->unit?->product?->thumbnail
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}