<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('delivery_address')->nullable()->after('notes');
            $table->decimal('delivery_distance_km', 8, 1)->nullable()->after('delivery_address');
            $table->integer('delivery_fee_price')->default(0)->after('delivery_distance_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['delivery_address', 'delivery_distance_km', 'delivery_fee_price']);
        });
    }
};