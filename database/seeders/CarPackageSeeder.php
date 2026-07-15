<?php

namespace Database\Seeders;

use App\Models\CarPackage;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CarPackageSeeder extends Seeder
{
    /**
     * Harga per mobil, per jenis paket (slug package => harga).
     * Mobil dicocokkan lewat slug yang dibuat di ProductSeeder
     * (Str::slug($name) . '-' . $model_year).
     */
    public function run(): void
    {
        $pricing = [
            'toyota-agya-2023' => [
                '12-jam' => 175_000,
                '24-jam' => 275_000,
                'mingguan' => 1_600_000,
                'bulanan' => 5_500_000,
            ],
            'toyota-avanza-2023' => [
                '12-jam' => 225_000,
                '24-jam' => 350_000,
                'mingguan' => 2_100_000,
                'bulanan' => 7_000_000,
            ],
            'toyota-rush-2023' => [
                '12-jam' => 300_000,
                '24-jam' => 450_000,
                'mingguan' => 2_700_000,
                'bulanan' => 9_000_000,
            ],
            'toyota-fortuner-2023' => [
                '12-jam' => 650_000,
                '24-jam' => 950_000,
                'mingguan' => 5_700_000,
                'bulanan' => 19_000_000,
            ],
            'toyota-alphard-2022' => [
                '12-jam' => 1_200_000,
                '24-jam' => 1_800_000,
                'mingguan' => 10_800_000,
                'bulanan' => 36_000_000,
            ],
        ];

        foreach ($pricing as $productSlug => $packagePrices) {
            $product = Product::where('slug', $productSlug)->first();

            if (! $product) {
                $this->command?->warn("Produk dengan slug \"{$productSlug}\" tidak ditemukan, dilewati.");
                continue;
            }

            foreach ($packagePrices as $packageSlug => $price) {
                $package = Package::where('slug', $packageSlug)->first();

                if (! $package) {
                    $this->command?->warn("Paket dengan slug \"{$packageSlug}\" tidak ditemukan, dilewati.");
                    continue;
                }

                CarPackage::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'package_id' => $package->id,
                    ],
                    [
                        'price' => $price,
                    ]
                );
            }
        }
    }
}