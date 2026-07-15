<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            ['name' => '12 Jam', 'slug' => '12-jam', 'duration_value' => 12, 'duration_unit' => 'hour', 'sort_order' => 1],
            ['name' => '24 Jam', 'slug' => '24-jam', 'duration_value' => 24, 'duration_unit' => 'hour', 'sort_order' => 2],
            ['name' => 'Mingguan', 'slug' => 'mingguan', 'duration_value' => 7, 'duration_unit' => 'day', 'sort_order' => 3],
            ['name' => 'Bulanan', 'slug' => 'bulanan', 'duration_value' => 30, 'duration_unit' => 'day', 'sort_order' => 4],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(['slug' => $package['slug']], $package);
        }
    }
}