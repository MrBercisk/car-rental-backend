<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    // refresh database tiap test jalan dengan database bersih (migration fresh)
    // biar test tidak terpengaruh data dari tes lainnya
    use RefreshDatabase;

    /* test method index */

    /* test hanya produk yang tersedia */
    public function test_index_hanya_mengembalikan_produk_yang_tersedia(): void
    {
        // data tes
        Product::factory()->create(['is_available' => true, 'name' => 'Avanza']);
        Product::factory()->create(['is_available' => false, 'name' => 'Innova']);

        // endpoint
        $response = $this->getJson('/api/v1/products');

        // cek response harusnya status ok, 1 data, index ke 0 dengan nama Avanza
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Avanza');
    }

    /* test filter berdasarkan category slug */
    public function test_index_bisa_difilter_berdasarkan_category(): void
    {
        $sedan = Category::factory()->create(['slug' => 'sedan']);
        $suv = Category::factory()->create(['slug' => 'suv']);

        Product::factory()->create(['is_available' => true, 'category_id' => $sedan->id]);
        Product::factory()->create(['is_available' => true, 'category_id' => $suv->id]);

        $response = $this->getJson('/api/v1/products?category=sedan');

        // cek response harusnya status ok dengan jumlah data 1 = sedan
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    /* test filter berdasarkan transmission */
    public function test_index_bisa_difilter_berdasarkan_transmission(): void
    {
        Product::factory()->create(['is_available' => true, 'transmission' => 'manual']);
        Product::factory()->create(['is_available' => true, 'transmission' => 'automatic']);

        $response = $this->getJson('/api/v1/products?transmission=automatic');

        /* cek response harusnya status ok jumlah data 1, data index ke 0 transmission nya automatic */
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transmission', 'automatic');
    }

    /* test filter featured */
    public function test_index_bisa_difilter_produk_featured(): void
    {
        Product::factory()->create(['is_available' => true, 'is_featured' => true]);
        Product::factory()->create(['is_available' => true, 'is_featured' => false]);

        $response = $this->getJson('/api/v1/products?featured=1');

         // cek response harusnya status ok dengan jumlah data 1 yang featured nya true
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    /* test search berdasarkan nama (like query) termasuk cek karakter spesial % dan _ */
    /* tidak menyebabkan error/hasil salah (SQL injection) */
    public function test_index_bisa_dicari_berdasarkan_nama(): void
    {
        Product::factory()->create(['is_available' => true, 'name' => 'Toyota Avanza']);
        Product::factory()->create(['is_available' => true, 'name' => 'Honda Brio']);

        $response = $this->getJson('/api/v1/products?search=avanza');

        // cek response harunysa status ok dengan jumlah data 1, return data pertama avanza
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Toyota Avanza');
    }

    /* test search karaketer persen tidak error  */
    public function test_search_dengan_karakter_persen_tidak_error(): void
    {
        Product::factory()->create(['is_available' => true, 'name' => 'Avanza 100%']);

        // search mengandung karakter % yang harusnya diescape, bukan dianggap wildcard SQL
        $response = $this->getJson('/api/v1/products?search=' . urlencode('100%'));

        $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Avanza 100%');
    }

    /* test maks per page 50 */
    public function test_per_page_dibatasi_maksimal_50(): void
    {
        // buat 60 produk
        Product::factory()->count(60)->create(['is_available' => true]);

        // user minta 999 data per page
        $response = $this->getJson('/api/v1/products?per_page=999');

        // cek response status ok dan jumlah data yang di return harus tidak lebih dari 50
        $response->assertOk();
        $this->assertLessThanOrEqual(50, count($response->json('data')));
    }

    /* test per page minimal 1 */
    public function test_per_page_dibatasi_minimal_1(): void
    {
        // buat 5 produk
        Product::factory()->count(5)->create(['is_available' => true]);

        // user minta 0 data per page
        $response = $this->getJson('/api/v1/products?per_page=0');

        // cek response harus ok dan harus return 1 produk
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    /* test default per page 12 kalau tidak mengirimkan parameter per page */
    public function test_per_page_default_12(): void
    {
        // buat 20 produk
        Product::factory()->count(20)->create(['is_available' => true]);

        $response = $this->getJson('/api/v1/products');

        // cek response ok dan return 12 produk
        $response->assertOk();
        $this->assertCount(12, $response->json('data'));
    }

    /* test urutan hasil sesuai sort_order(produk mana dulu yang diurutkan)*/
    public function test_index_terurut_berdasarkan_sort_order(): void
    {
        Product::factory()->create(['is_available' => true, 'name' => 'Mobil Kedua', 'sort_order' => 2]);
        Product::factory()->create(['is_available' => true, 'name' => 'Mobil Pertama', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/products');

        // cek response harusnya ok dan produk pertama harusnya mobil pertama, data ke dua harusnya mobil kedua
        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Mobil Pertama')
            ->assertJsonPath('data.1.name', 'Mobil Kedua');
    }

   
    /* test method show */

    /* test mengambil produk berdasarkan slug nya */
    public function test_show_mengembalikan_produk_berdasarkan_slug(): void
    {
        // buat produk
        Product::factory()->create([
            'slug' => 'toyota-avanza',
            'is_available' => true,
        ]);

        // ambil produk
        $response = $this->getJson('/api/v1/products/toyota-avanza');

        // cek response harusnya ok , dengan slug bernama toyota-avanza
        $response->assertOk()
            ->assertJsonPath('data.slug', 'toyota-avanza');
    }

    /* test slug produk tidak ditemukan */
    public function test_show_mengembalikan_404_jika_slug_tidak_ditemukan(): void
    {
        $response = $this->getJson('/api/v1/products/tidak-ada-slug-ini');

        $response->assertNotFound();
    }

    /* test produk yang tidak tersedia */
    public function test_show_mengembalikan_404_jika_produk_tidak_tersedia(): void
    {
        Product::factory()->create([
            'slug' => 'avanza-nonaktif',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/v1/products/avanza-nonaktif');

        $response->assertNotFound();
    }
}