<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'City Car',
                'description' => 'Mobil kompak dan lincah, cocok untuk perjalanan dalam kota.',
            ],
            [
                'name' => 'MPV',
                'description' => 'Mobil keluarga dengan kapasitas penumpang luas dan nyaman.',
            ],
            [
                'name' => 'SUV',
                'description' => 'Mobil tangguh dengan ground clearance tinggi, cocok untuk luar kota.',
            ],
            [
                'name' => 'Minibus / Elf',
                'description' => 'Kendaraan kapasitas besar untuk rombongan dan perjalanan grup.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'image' => null,
                ]
            );
        }
    }
}