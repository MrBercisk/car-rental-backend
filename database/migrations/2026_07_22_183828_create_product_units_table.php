<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('license_plate')->unique();
            $table->enum('condition_status', ['active', 'maintenance', 'inactive'])
                ->default('active');
            $table->text('notes')->nullable(); // catatan servis, kondisi, dst

            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};