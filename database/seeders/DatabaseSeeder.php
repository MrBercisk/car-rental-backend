<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            CategorySeeder::class,
            PackageSeeder::class,
            ProductSeeder::class,
            CarPackageSeeder::class,
            RentalCmsSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@rental.com',
        ]);
    }
}