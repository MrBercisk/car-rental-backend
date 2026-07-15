<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'category' => 'City Car',
                'name' => 'Toyota Agya',
                'brand' => 'Toyota',
                'model_year' => '2023',
                'transmission' => 'manual',
                'fuel_type' => 'bensin',
                'seat_capacity' => 5,
                'luggage_capacity' => 2,
                'description' => 'Mobil city car irit BBM, mudah dikendarai di jalanan perkotaan yang padat.',
                'features' => ['AC Dingin', 'Audio Bluetooth', 'Power Window', 'Central Lock'],
                'thumbnail' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=80',
                'is_featured' => true,
            ],
            [
                'category' => 'MPV',
                'name' => 'Toyota Avanza',
                'brand' => 'Toyota',
                'model_year' => '2023',
                'transmission' => 'manual',
                'fuel_type' => 'bensin',
                'seat_capacity' => 7,
                'luggage_capacity' => 4,
                'description' => 'MPV keluarga paling populer di Indonesia, kabin lega untuk 7 penumpang.',
                'features' => ['AC Double Blower', 'Audio Bluetooth', 'Power Window', 'Central Lock'],
                'thumbnail' => 'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?auto=format&fit=crop&w=800&q=80',
                'is_featured' => true,
            ],
            [
                'category' => 'SUV',
                'name' => 'Toyota Rush',
                'brand' => 'Toyota',
                'model_year' => '2023',
                'transmission' => 'automatic',
                'fuel_type' => 'bensin',
                'seat_capacity' => 7,
                'luggage_capacity' => 4,
                'description' => 'SUV kompak dengan ground clearance tinggi, gesit di jalan menanjak.',
                'features' => ['AC Double Blower', 'Audio Touchscreen', 'Kamera Belakang'],
                'thumbnail' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=800&q=80',
                'is_featured' => true,
            ],
            [
                'category' => 'SUV',
                'name' => 'Toyota Fortuner',
                'brand' => 'Toyota',
                'model_year' => '2023',
                'transmission' => 'automatic',
                'fuel_type' => 'diesel',
                'seat_capacity' => 7,
                'luggage_capacity' => 5,
                'description' => 'SUV premium favorit korporat, gagah dan nyaman untuk perjalanan luar kota.',
                'features' => ['AC Double Blower', 'Captain Seat', 'Audio Premium', 'Cruise Control'],
                'thumbnail' => 'https://images.unsplash.com/photo-1600661653561-629509216228?auto=format&fit=crop&w=800&q=80',
                'is_featured' => true,
            ],
            [
                'category' => 'SUV',
                'name' => 'Toyota Alphard',
                'brand' => 'Toyota',
                'model_year' => '2022',
                'transmission' => 'automatic',
                'fuel_type' => 'bensin',
                'seat_capacity' => 7,
                'luggage_capacity' => 5,
                'description' => 'MPV mewah kelas VVIP, pilihan utama untuk tamu penting dan acara khusus.',
                'features' => ['Captain Seat Elektrik', 'AC Double Blower', 'Audio Premium', 'Sunroof'],
                'thumbnail' => 'https://images.unsplash.com/photo-1617531653332-bd46c24f2068?auto=format&fit=crop&w=800&q=80',
                'is_featured' => true,
            ],
        ];

        foreach ($products as $index => $item) {
            $category = Category::where('slug', Str::slug($item['category']))->first();

            Product::updateOrCreate(
                ['slug' => Str::slug($item['name']) . '-' . $item['model_year']],
                [
                    'name' => $item['name'],
                    'brand' => $item['brand'],
                    'model_year' => $item['model_year'],
                    'transmission' => $item['transmission'],
                    'fuel_type' => $item['fuel_type'],
                    'seat_capacity' => $item['seat_capacity'],
                    'luggage_capacity' => $item['luggage_capacity'],
                    'description' => $item['description'],
                    'features' => $item['features'],
                    'images' => [$item['thumbnail']],
                    'is_available' => true,
                    'is_featured' => $item['is_featured'],
                    'category_id' => $category?->id,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}