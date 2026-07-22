<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // closing / lock — begitu terisi, field finansial tidak boleh diedit lagi lewat form
            $table->timestamp('locked_at')->nullable()->after('expired_at');
        });

        // amount_paid sekarang berperan sebagai CACHE (sumber kebenaran di booking_payments),
        // jadi baris yang masih null dirapikan jadi 0 dulu
        DB::table('bookings')->whereNull('amount_paid')->update(['amount_paid' => 0]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_paid')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('locked_at');
            $table->unsignedBigInteger('amount_paid')->nullable()->default(null)->change();
        });
    }
};