<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 100000),
            'brand' => fake()->company(),
            'model_year' => fake()->numberBetween(2015, 2025),
            'transmission' => fake()->randomElement(['manual', 'automatic']),
            'fuel_type' => fake()->randomElement(['bensin', 'diesel', 'listrik']),
            'seat_capacity' => fake()->numberBetween(2, 8),
            'luggage_capacity' => fake()->numberBetween(1, 5),
            'license_plate' => strtoupper(fake()->bothify('B #### ??')),
            'description' => fake()->paragraph(),
            'features' => fake()->randomElements(
                ['AC', 'Audio System', 'GPS', 'Airbag', 'Rear Camera'],
                2
            ),
            'images' => [fake()->imageUrl()],
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}