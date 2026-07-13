<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class RentalCmsSeeder extends Seeder
{
    public function run(): void
    {
        // Pengaturan default website
        $settings = [
            'site_name' => ['Rental Kendaraan Kita', 'general'],
            'site_tagline' => ['Sewa Mobil Nyaman & Terpercaya', 'general'],
            'site_description' => ['Layanan rental mobil untuk perjalanan wisata maupun harian Anda.', 'general'],
            'contact_phone' => ['+62 812-3456-7890', 'contact'],
            'contact_email' => ['info@rentalkita.com', 'contact'],
            'contact_address' => ['Jl. Contoh Alamat No. 123, Yogyakarta', 'contact'],
            'social_instagram' => ['https://instagram.com/', 'social'],
            'social_facebook' => ['https://facebook.com/', 'social'],
            'seo_meta_title' => ['Rental Kendaraan Kita - Sewa Mobil Terpercaya', 'seo'],
            'seo_meta_description' => ['Sewa mobil harian, mingguan, dan bulanan dengan harga terbaik.', 'seo'],
        ];

        foreach ($settings as $key => [$value, $group]) {
            Setting::set($key, $value, $group);
        }

        // Kategori contoh
        $categories = ['City Car', 'MPV', 'SUV', 'Minibus / Elf'];
        foreach ($categories as $i => $name) {
            Category::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => $i]
            );
        }

        // Testimoni contoh
        $testimonials = [
            ['name' => 'Budi Santoso', 'role' => 'Wisatawan dari Jakarta', 'rating' => 5, 'message' => 'Pelayanan sangat memuaskan, mobil bersih dan sopir ramah.'],
            ['name' => 'Sari Wulandari', 'role' => 'Karyawan Swasta', 'rating' => 5, 'message' => 'Proses booking cepat dan harga sangat bersaing.'],
        ];
        foreach ($testimonials as $i => $t) {
            Testimonial::firstOrCreate(
                ['name' => $t['name']],
                [...$t, 'is_active' => true, 'sort_order' => $i]
            );
        }
    }
}
