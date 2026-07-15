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
        Schema::create('car_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('package_id')
                ->constrained('packages')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('price'); // harga dasar untuk paket ini, belum termasuk supir
            $table->timestamps();

            // Satu mobil hanya boleh punya satu harga per jenis paket
            $table->unique(['product_id', 'package_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_package');
    }
};