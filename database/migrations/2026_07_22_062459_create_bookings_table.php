<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // rentang sewa
            $table->date('start_date');
            $table->date('end_date');
            
             // paket sewa yang dipilih (snapshot, biar gak berubah kalau harga master diubah)
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('package_label')->nullable();
            $table->unsignedBigInteger('package_price')->nullable();

            // status booking internal
            // pending    = baru masuk / belum ada tindakan
            // dp         = sudah bayar DP
            // lunas      = sudah lunas
            // confirmed  = dikonfirmasi admin tanpa pembayaran (khusus WA manual)
            // cancelled  = dibatalkan
            $table->enum('status', ['pending', 'dp', 'lunas', 'confirmed', 'cancelled'])
                ->default('pending');

            // data customer
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();

            // pembayaran manual (upload bukti transfer, tanpa gateway)
            $table->string('payment_proof_path')->nullable();
            $table->unsignedBigInteger('amount_paid')->nullable();

            // asal booking
            $table->enum('source', ['whatsapp', 'form', 'admin'])->default('whatsapp');

            // kolom buat payment gateway (Midtrans/Doku/Xendit dll)
            // semua nullable — tidak dipakai sama sekali kalau booking manual/WA
            $table->string('payment_gateway')->nullable(); // midtrans, doku, xendit
            $table->string('gateway_order_id')->nullable()->unique(); // dikirim ke gateway sbg order_id/invoice
            $table->string('gateway_transaction_id')->nullable(); // transaction_id balikan dari gateway
            $table->string('gateway_status')->nullable(); // status dr gateway: settlement/expire/deny/cancel
            $table->string('gateway_payment_method')->nullable(); // bank_transfer, qris, gopay
            $table->unsignedBigInteger('gross_amount')->nullable(); // jumlah tagihan menurut gateway
            $table->string('snap_token')->nullable(); // token Snap Midtrans / redirect token gateway lain
            $table->string('payment_redirect_url')->nullable(); // url pembayaran Doku xendit
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('gateway_payload')->nullable(); // simpan raw callback/webhook, buat debug/dispute

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};