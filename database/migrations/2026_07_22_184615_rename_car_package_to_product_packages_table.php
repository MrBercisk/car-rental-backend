<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('car_package', 'product_packages');
    }

    public function down(): void
    {
        Schema::rename('product_packages', 'car_package');
    }
};