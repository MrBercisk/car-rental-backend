<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom product_unit_id kalau belum ada
        if (! Schema::hasColumn('bookings', 'product_unit_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('product_unit_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_units')
                    ->nullOnDelete();
            });
        }

        // 2. Migrasi data lama (aman dijalankan berkali-kali, cuma re-assign)
        if (Schema::hasColumn('bookings', 'product_id')) {
            $bookings = DB::table('bookings')->whereNotNull('product_id')->get();

            foreach ($bookings as $booking) {
                $unit = DB::table('product_units')
                    ->where('product_id', $booking->product_id)
                    ->first();

                if ($unit) {
                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update(['product_unit_id' => $unit->id]);
                }
            }
        }

        // 3. Drop foreign key product_id dulu (kalau ada), baru index-nya,
        //    baru kolomnya -- urutan ini WAJIB karena index tidak bisa
        //    dihapus selama masih dipakai foreign key constraint.
        if (Schema::hasColumn('bookings', 'product_id')) {
            $fkExists = DB::selectOne("
                SELECT COUNT(*) as total FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'bookings'
                AND CONSTRAINT_NAME = 'bookings_product_id_foreign'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ")->total;

            if ($fkExists > 0) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                });
            }

            $indexExists = DB::selectOne("
                SELECT COUNT(*) as total FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'bookings'
                AND INDEX_NAME = 'bookings_product_id_start_date_end_date_index'
            ")->total;

            if ($indexExists > 0) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropIndex(['product_id', 'start_date', 'end_date']);
                });
            }

            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });
        }

        // 4. Tambah index baru kalau belum ada
        $newIndexExists = DB::selectOne("
            SELECT COUNT(*) as total FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'bookings'
            AND INDEX_NAME = 'bookings_product_unit_id_start_date_end_date_index'
        ")->total;

        if ($newIndexExists === 0) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index(['product_unit_id', 'start_date', 'end_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['product_unit_id', 'start_date', 'end_date']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn('product_unit_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['product_id', 'start_date', 'end_date']);
        });
    }
};