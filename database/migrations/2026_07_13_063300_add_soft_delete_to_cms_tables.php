<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('products', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('banners', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('testimonials', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('products', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('banners', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('testimonials', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};