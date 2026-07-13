<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand')->nullable();
            $table->string('model_year')->nullable();
            $table->enum('transmission', ['manual', 'automatic'])->default('manual');
            $table->enum('fuel_type', ['bensin', 'diesel', 'listrik', 'hybrid'])->default('bensin');
            $table->unsignedTinyInteger('seat_capacity')->default(4);
            $table->unsignedTinyInteger('luggage_capacity')->nullable();
            $table->decimal('price_per_day', 12, 2);
            $table->decimal('price_per_day_with_driver', 12, 2)->nullable();
            $table->string('license_plate')->nullable();
            $table->text('description')->nullable();
            // Daftar fitur mobil, contoh: ["AC","Audio System","GPS"]
            $table->json('features')->nullable();
            // Daftar path gambar, gambar pertama otomatis jadi thumbnail
            $table->json('images')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
