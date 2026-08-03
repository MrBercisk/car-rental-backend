<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah 'payment_gateway' ke enum source untuk booking via gateway pembayaran
        DB::statement("ALTER TABLE bookings MODIFY COLUMN source ENUM('whatsapp', 'form', 'admin', 'maintenance', 'payment_gateway') NOT NULL DEFAULT 'whatsapp'");
    }

    public function down(): void
    {
        // Sebelum revert, pastikan tidak ada baris dengan source = 'payment_gateway'
        DB::table('bookings')->where('source', 'payment_gateway')->update(['source', 'form']);

        DB::statement("ALTER TABLE bookings MODIFY COLUMN source ENUM('whatsapp', 'form', 'admin', 'maintenance') NOT NULL DEFAULT 'whatsapp'");
    }
};
