<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $products = DB::table('products')->whereNotNull('license_plate')->get();

        foreach ($products as $product) {
            DB::table('product_units')->insert([
                'product_id' => $product->id,
                'license_plate' => $product->license_plate,
                'condition_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('license_plate');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('license_plate')->nullable()->after('model_year');
        });
    }
};