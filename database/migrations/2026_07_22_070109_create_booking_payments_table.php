<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount');
            $table->enum('type', ['dp', 'pelunasan', 'refund', 'penyesuaian'])->default('dp');
            $table->date('paid_at');

            $table->string('method')->nullable(); // transfer, cash, qris, gateway, dst
            $table->string('proof_path')->nullable();
            $table->text('note')->nullable();

            // dari gateway
            $table->string('gateway_transaction_id')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['booking_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};