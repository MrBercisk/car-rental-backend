<?php

namespace Tests\Feature\Api;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialControllerTest extends TestCase
{
    use RefreshDatabase;

    /* test method index */

    /* test hanya testimoni yang aktif yang ditampilkan */
    public function test_index_hanya_mengembalikan_testimoni_yang_aktif(): void
    {
        Testimonial::factory()->create(['is_active' => true, 'name' => 'Budi']);
        Testimonial::factory()->create(['is_active' => false, 'name' => 'Ani']);

        $response = $this->getJson('/api/v1/testimonials');

        // cek response ok, jumlah data 1, dan itu yang aktif
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi');
    }

    /* test urutan sesuai sort_order */
    public function test_index_terurut_berdasarkan_sort_order(): void
    {
        Testimonial::factory()->create(['is_active' => true, 'name' => 'Testimoni Kedua', 'sort_order' => 2]);
        Testimonial::factory()->create(['is_active' => true, 'name' => 'Testimoni Pertama', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/testimonials');

        // cek data pertama harusnya "Testimoni Pertama", data kedua "Testimoni Kedua"
        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Testimoni Pertama')
            ->assertJsonPath('data.1.name', 'Testimoni Kedua');
    }

    /* test kalau gak ada testimoni aktif sama sekali, return array kosong bukan error */
    public function test_index_return_array_kosong_kalau_tidak_ada_testimoni_aktif(): void
    {
        Testimonial::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/testimonials');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    /* test rating ikut ke-return di response (bukan cuma cek jumlah/nama doang) */
    public function test_index_mengembalikan_rating_yang_benar(): void
    {
        Testimonial::factory()->create(['is_active' => true, 'name' => 'Budi', 'rating' => 5]);

        $response = $this->getJson('/api/v1/testimonials');

        $response->assertOk()->assertJsonPath('data.0.rating', 5);
    }
}