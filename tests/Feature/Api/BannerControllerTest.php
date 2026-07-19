<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerControllerTest extends TestCase
{
    use RefreshDatabase;

    /* test method index */

    /* test hanya banner yang aktif yang ditampilkan */
    public function test_index_hanya_mengembalikan_banner_yang_aktif(): void
    {
        Banner::factory()->create(['is_active' => true, 'title' => 'Promo Lebaran']);
        Banner::factory()->create(['is_active' => false, 'title' => 'Promo Expired']);

        $response = $this->getJson('/api/v1/banners');

        // cek response ok, jumlah data 1, dan itu yang aktif
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Promo Lebaran');
    }

    /* test urutan sesuai sort_order */
    public function test_index_terurut_berdasarkan_sort_order(): void
    {
        Banner::factory()->create(['is_active' => true, 'title' => 'Banner Kedua', 'sort_order' => 2]);
        Banner::factory()->create(['is_active' => true, 'title' => 'Banner Pertama', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/banners');

        // cek data pertama harusnya "Banner Pertama", data kedua "Banner Kedua"
        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Banner Pertama')
            ->assertJsonPath('data.1.title', 'Banner Kedua');
    }

    /* test kalau gak ada banner aktif sama sekali, return array kosong bukan error */
    public function test_index_return_array_kosong_kalau_tidak_ada_banner_aktif(): void
    {
        Banner::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/banners');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}