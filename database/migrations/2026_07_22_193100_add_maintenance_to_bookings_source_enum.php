<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL tidak punya cara native "tambah value ke enum" lewat Schema
        // builder, jadi pakai raw statement MODIFY COLUMN.
        // SQLite tidak punya sintaks MODIFY COLUMN maupun tipe ENUM --
        // di SQLite kolom ini sudah tersimpan sebagai TEXT tanpa constraint,
        // jadi value baru otomatis bisa masuk tanpa perlu ALTER apapun.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN source ENUM('whatsapp', 'form', 'admin', 'maintenance') NOT NULL DEFAULT 'whatsapp'");
        }
    }

    public function down(): void
    {
        // Sebelum revert, pastikan tidak ada baris dengan source = 'maintenance'
        // supaya tidak error saat enum-nya dipersempit lagi.
        DB::table('bookings')->where('source', 'maintenance')->update(['source' => 'admin']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN source ENUM('whatsapp', 'form', 'admin') NOT NULL DEFAULT 'whatsapp'");
        }
    }
};