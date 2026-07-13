<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'model_year' => $this->model_year,
            'transmission' => $this->transmission,
            'fuel_type' => $this->fuel_type,
            'seat_capacity' => $this->seat_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'price_per_day' => (float) $this->price_per_day,
            'price_per_day_with_driver' => $this->price_per_day_with_driver ? (float) $this->price_per_day_with_driver : null,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'images' => collect($this->images ?? [])->map(fn ($img) => asset('storage/' . $img))->values(),
            'thumbnail' => $this->thumbnail ? asset('storage/' . $this->thumbnail) : null,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
