<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    /* test method index */

    /* test cuma key yang di whitelist yang ke-return */
    public function test_index_hanya_mengembalikan_key_yang_di_whitelist(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Rental Mobil Semarang', 'group' => 'general']);
        // key ini SENGAJA gak ada di PUBLIC_KEYS controller, harusnya gak ke-expose
        Setting::create(['key' => 'internal_api_secret', 'value' => 'rahasia-banget', 'group' => 'internal']);

        $response = $this->getJson('/api/v1/settings');

        // cek key yang whitelisted muncul, key yang gak di whitelist gak muncul di response
        $response->assertOk()
            ->assertJsonPath('data.site_name', 'Rental Mobil Semarang')
            ->assertJsonMissingPath('data.internal_api_secret');
    }

    /* test site_logo diubah jadi full url pakai asset() */
    public function test_site_logo_diubah_jadi_full_url(): void
    {
        Setting::create(['key' => 'site_logo', 'value' => 'logos/logo.png', 'group' => 'general']);

        $response = $this->getJson('/api/v1/settings');

        // cek value-nya udah bukan path mentah lagi, tapi full url yang mengandung 'storage/'
        $response->assertOk();
        $logoUrl = $response->json('data.site_logo');
        $this->assertStringContainsString('storage/logos/logo.png', $logoUrl);
    }

    /* test site_favicon juga diubah jadi full url, sama kayak site_logo */
    public function test_site_favicon_diubah_jadi_full_url(): void
    {
        Setting::create(['key' => 'site_favicon', 'value' => 'favicons/favicon.ico', 'group' => 'general']);

        $response = $this->getJson('/api/v1/settings');

        $favicon = $response->json('data.site_favicon');
        $this->assertStringContainsString('storage/favicons/favicon.ico', $favicon);
    }

    /* test kalau site_logo kosong/gak diset, gak boleh error atau ke-convert jadi url aneh */
    public function test_site_logo_kosong_tidak_menyebabkan_error(): void
    {
        // sama sekali gak bikin setting site_logo

        $response = $this->getJson('/api/v1/settings');

        // harusnya tetep 200, bukan 500
        $response->assertOk();
    }

    /* test field selain site_logo/site_favicon TIDAK ikut di-convert jadi url */
    public function test_field_selain_logo_favicon_tidak_ikut_dikonversi_jadi_url(): void
    {
        Setting::create(['key' => 'contact_email', 'value' => 'info@rental.com', 'group' => 'contact']);

        $response = $this->getJson('/api/v1/settings');

        // value contact_email harusnya tetep apa adanya, bukan ketimpa asset()
        $response->assertOk()
            ->assertJsonPath('data.contact_email', 'info@rental.com');
    }

    /* test index tetep return 200 dengan data kosong kalau belum ada setting sama sekali */
    public function test_index_return_ok_kalau_belum_ada_setting(): void
    {
        $response = $this->getJson('/api/v1/settings');

        $response->assertOk()->assertJsonStructure(['data']);
    }
}