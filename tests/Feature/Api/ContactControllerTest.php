<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    /* test method store */

    /* test happy path, data lengkap dan valid */
    public function test_store_berhasil_dengan_data_lengkap(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'subject' => 'Tanya sewa mobil',
            'message' => 'Halo, saya mau tanya ketersediaan mobil untuk akhir pekan ini.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        // cek response harusnya status 201 dan pesan sukses sesuai controller
        $response->assertCreated()
            ->assertJson([
                'message' => 'Pesan Anda berhasil dikirim. Kami akan segera menghubungi Anda.',
            ]);

        // cek juga row-nya beneran kesimpen di db, bukan cuma response-nya doang yang bener
        $this->assertDatabaseHas('contacts', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);
    }

    /* test field nullable (phone, subject) boleh kosong/gak dikirim */
    public function test_store_berhasil_tanpa_phone_dan_subject(): void
    {
        $payload = [
            'name' => 'Ani',
            'email' => 'ani@example.com',
            'message' => 'Pesan tanpa nomor telepon dan subjek.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        // cek response ok dan phone null di db
        $response->assertCreated();
        $this->assertDatabaseHas('contacts', ['name' => 'Ani', 'phone' => null]);
    }

    /* test validasi field wajib */

    /* test gagal kalau name kosong */
    public function test_store_gagal_jika_name_kosong(): void
    {
        $payload = [
            'email' => 'test@example.com',
            'message' => 'Pesan tanpa nama.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        // cek response 422 dengan error di field name
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // pastikan gak ada row yang kesimpen meski validasi gagal
        $this->assertDatabaseCount('contacts', 0);
    }

    /* test gagal kalau email kosong */
    public function test_store_gagal_jika_email_kosong(): void
    {
        $payload = [
            'name' => 'Budi',
            'message' => 'Pesan tanpa email.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        // cek response 422 dengan error di field email
        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /* test gagal kalau format email salah */
    public function test_store_gagal_jika_email_formatnya_salah(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'bukan-email-valid',
            'message' => 'Pesan dengan email salah format.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /* test gagal kalau message kosong */
    public function test_store_gagal_jika_message_kosong(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'budi@example.com',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['message']);
    }

    /* test batas maksimal panjang field (max:255 / max:5000) */

    /* test gagal kalau name lebih dari 255 karakter */
    public function test_store_gagal_jika_name_melebihi_255_karakter(): void
    {
        $payload = [
            'name' => str_repeat('a', 256), // 1 karakter lebih dari batas
            'email' => 'budi@example.com',
            'message' => 'Pesan singkat.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /* test gagal kalau message lebih dari 5000 karakter */
    public function test_store_gagal_jika_message_melebihi_5000_karakter(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'message' => str_repeat('a', 5001),
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['message']);
    }

    /* test berhasil kalau message pas 5000 karakter (cek batas persis, bukan cuma di atas/bawahnya) */
    public function test_store_berhasil_jika_message_tepat_5000_karakter(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'message' => str_repeat('a', 5000),
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertCreated();
    }

    /* test keamanan: field di luar rules validasi (misal is_read) gak boleh ke-set lewat request */
    public function test_field_di_luar_validasi_tidak_ikut_tersimpan(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'message' => 'Pesan biasa.',
            'is_read' => true, // field ini gak ada di rules validasi
        ];

        $this->postJson('/api/v1/contact', $payload)->assertCreated();

        $contact = Contact::first();

        // is_read harusnya tetap default (false), bukan true dari request,
        // karena controller cuma pake $validated, bukan $request->all()
        $this->assertFalse((bool) $contact->is_read);
    }

    /* test method throttle */

    /* test rate limit ngeblok kalau request kebanyakan */
    public function test_throttle_membatasi_request_yang_terlalu_sering(): void
    {
        // limit asli di AppServiceProvider: Limit::perMinutes(10, 3)
        // maksimal 3 request per 10 menit, key-nya ip + email
        $payload = [
            'name' => 'Spammer',
            'email' => 'spam@example.com',
            'message' => 'Pesan spam berulang-ulang.',
        ];

        // 3 request pertama harus tetap lolos
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/contact', $payload)->assertCreated();
        }

        // request ke 4 dengan ip + email yang sama harus kena limit
        $response = $this->postJson('/api/v1/contact', $payload);
        $response->assertStatus(429);
    }

    /* test key limiter beneran ip + email, bukan cuma ip */
    public function test_throttle_tidak_berlaku_lintas_email_berbeda(): void
    {
        $emailPertama = ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Pesan A.'];
        $emailKedua = ['name' => 'B', 'email' => 'b@example.com', 'message' => 'Pesan B.'];

        // habisin limit buat email pertama
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/contact', $emailPertama)->assertCreated();
        }
        $this->postJson('/api/v1/contact', $emailPertama)->assertStatus(429);

        // email kedua (ip sama, email beda) harusnya masih boleh
        $this->postJson('/api/v1/contact', $emailKedua)->assertCreated();
    }
}