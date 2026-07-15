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
            'description' => $this->description,
            'features' => $this->features ?? [],
            'images' => collect($this->images ?? [])->map(fn ($img) => $this->resolveImageUrl($img))->values(),
            'thumbnail' => $this->thumbnail ? $this->resolveImageUrl($this->thumbnail) : null,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'packages' => $this->whenLoaded('packages', function () {
                return $this->packages
                    ->filter(fn ($cp) => $cp->package !== null)
                    ->map(fn ($cp) => [
                        'id' => $cp->id,
                        'price' => (float) $cp->price,
                        'package' => [
                            'id' => $cp->package->id,
                            'name' => $cp->package->name,
                            'slug' => $cp->package->slug,
                            'duration_value' => $cp->package->duration_value,
                            'duration_unit' => $cp->package->duration_unit,
                        ],
                    ])
                    ->values();
            }, []),
        ];
    }

    private function resolveImageUrl(string $img): string
    {
        return str_starts_with($img, 'http://') || str_starts_with($img, 'https://')
            ? $img
            : asset('storage/' . $img);
    }
}