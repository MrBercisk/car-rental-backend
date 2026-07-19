<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    // refresh database tiap test jalan dengan database bersih (migration fresh)
    // biar test tidak terpengaruh data dari tes lainnya
    use RefreshDatabase;

    /* test method index */

    /* test hanya kategori yang aktif */
   
    public function test_index_hanya_kategori_yang_aktif():void{
        // data tes
        Category::factory()->create(['is_active' => true, 'name' => 'Sedan']);
        Category::factory()->create(['is_active' => false, 'name' => 'SUV']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Sedan');
    }

    /* test kategori diurutkan berdasarkan sort order */
    public function test_index_terurut_berdasarkan_sort_order(): void
    {
        Category::factory()->create([
            'name' => 'Kedua',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        Category::factory()->create([
            'name' => 'Pertama',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $response = $this->getJson('/api/v1/categories');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Pertama')
            ->assertJsonPath('data.1.name', 'Kedua');

    }
    /* test struktur kategori */
    public function test_index_mengembalikan_struktur_kategori(): void
    {
        Category::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'image',
                        'products_count',
                    ],
                ],
            ]);
    }

    /* test count produk sesuai jumlah produk */
    public function test_products_count_sesuai_jumlah_produk(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.products_count', 3);
    }

     /* test detail kategori berdasarkan slug */
    public function test_show_mengembalikan_detail_kategori(): void
    {
        $category = Category::factory()->create([
            'name' => 'SUV',
            'slug' => 'suv',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->slug}");

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'SUV')
            ->assertJsonPath('data.slug', 'suv');
    }

    /* test slug yang tidak ada */
    public function test_show_slug_tidak_ditemukan(): void
    {
        $response = $this->getJson('/api/v1/categories/tidak-ada');

        $response->assertNotFound();
    }

    /* test kategori nonaktif tidak bisa diakses */
    public function test_show_kategori_nonaktif_tidak_bisa_diakses(): void
    {
        $category = Category::factory()->create([
            'slug' => 'mpv',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->slug}");

        $response->assertNotFound();
    }
}