<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'brand', 'model_year', 'transmission',
        'fuel_type', 'seat_capacity', 'luggage_capacity', 'license_plate', 'description',
        'features', 'images', 'is_available', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'images' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name) . '-' . Str::random(4);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function packages(): HasMany
    {
        return $this->hasMany(CarPackage::class)->with('package')->orderBy('id');
    }

    public function getThumbnailAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }
    
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
