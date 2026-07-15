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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // "12 Jam", "24 Jam", "Mingguan", "Bulanan"
            $table->string('slug')->unique();        // "12-jam", "24-jam", "mingguan", "bulanan"
            $table->unsignedInteger('duration_value'); // 12, 24, 7, 30
            $table->enum('duration_unit', ['hour', 'day']);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};