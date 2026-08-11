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
            if ($this->foreignKeyExists('bookings', 'bookings_product_id_foreign')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                });
            }

            if ($this->indexExists('bookings', 'bookings_product_id_start_date_end_date_index')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropIndex(['product_id', 'start_date', 'end_date']);
                });
            }

            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });
        }

        // 4. Tambah index baru kalau belum ada
        if (! $this->indexExists('bookings', 'bookings_product_unit_id_start_date_end_date_index')) {
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

    /**
     * Cek apakah foreign key dengan nama tertentu ada di tabel.
     * Database-agnostic: pakai information_schema di MySQL,
     * pakai PRAGMA foreign_key_list di SQLite.
     */
    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite tidak menyimpan nama constraint custom, jadi kita
            // cocokkan berdasarkan kolom yang dipakai FK-nya.
            $column = str($foreignKeyName)
                ->after($table . '_')
                ->beforeLast('_foreign')
                ->toString();

            $foreignKeys = DB::select("PRAGMA foreign_key_list(\"{$table}\")");

            return collect($foreignKeys)->contains(fn ($fk) => $fk->from === $column);
        }

        if ($driver === 'mysql') {
            $total = DB::selectOne("
                SELECT COUNT(*) as total FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ", [$table, $foreignKeyName])->total;

            return $total > 0;
        }

        if ($driver === 'pgsql') {
            $total = DB::selectOne("
                SELECT COUNT(*) as total FROM information_schema.table_constraints
                WHERE table_name = ?
                AND constraint_name = ?
                AND constraint_type = 'FOREIGN KEY'
            ", [$table, $foreignKeyName])->total;

            return $total > 0;
        }

        // Driver tidak dikenal: anggap tidak ada, biar tidak crash.
        return false;
    }

    /**
     * Cek apakah index dengan nama tertentu ada di tabel.
     * Database-agnostic: pakai information_schema di MySQL,
     * pakai PRAGMA index_list di SQLite.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list(\"{$table}\")");

            return collect($indexes)->contains(fn ($idx) => $idx->name === $indexName);
        }

        if ($driver === 'mysql') {
            $total = DB::selectOne("
                SELECT COUNT(*) as total FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?
            ", [$table, $indexName])->total;

            return $total > 0;
        }

        if ($driver === 'pgsql') {
            $total = DB::selectOne("
                SELECT COUNT(*) as total FROM pg_indexes
                WHERE tablename = ?
                AND indexname = ?
            ", [$table, $indexName])->total;

            return $total > 0;
        }

        return false;
    }
};