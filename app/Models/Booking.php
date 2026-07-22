<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'product_unit_id',
        'start_date',
        'end_date',
        'package_id',
        'package_label',
        'package_price',
        'status',
        'customer_name',
        'customer_phone',
        'notes',
        'payment_proof_path',
        'amount_paid',
        'source',
        // gateway
        'payment_gateway',
        'gateway_order_id',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_payment_method',
        'gross_amount',
        'snap_token',
        'payment_redirect_url',
        'paid_at',
        'expired_at',
        'gateway_payload',
        'locked_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount_paid' => 'integer',
        'gross_amount' => 'integer',
        'package_price' => 'integer',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'gateway_payload' => 'array',
        'locked_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function getProductAttribute(): ?Product
    {
        return $this->unit?->product;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->orderBy('paid_at');
    }

    public function scopeOverlapping($query, int $productUnitId, $start, $end, ?int $exceptId = null)
    {
        return $query->where('product_unit_id', $productUnitId)
            ->whereIn('status', ['pending', 'dp', 'lunas', 'confirmed'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId));
    }

    /**
     * Sumber kebenaran nominal terbayar ada di tabel booking_payments (append-only ledger).
     * amount_paid di sini hanya CACHE, dihitung ulang tiap ada pembayaran baru/koreksi.
     * Dipanggil otomatis lewat event di model BookingPayment.
     *
     * Sekaligus meng-update status booking (pending -> dp -> lunas) secara
     * otomatis mengikuti progress pembayaran, SUPAYA admin tidak perlu
     * ubah status manual tiap kali catat pembayaran. Lihat determineStatusFromPayment().
     */
    public function recalculateAmountPaid(): void
    {
        $total = $this->payments()
            ->whereIn('type', ['dp', 'pelunasan', 'penyesuaian'])
            ->sum('amount');

        $refund = $this->payments()->where('type', 'refund')->sum('amount');
        $amountPaid = max(0, $total - $refund);

        // Update amount_paid secara "silent" (tidak tercatat di activity log)
        // karena histori pembayaran sudah lengkap ada di booking_payments.
        $this->updateQuietly(['amount_paid' => $amountPaid]);

        $newStatus = $this->determineStatusFromPayment($amountPaid);

        if ($newStatus !== null && $newStatus !== $this->status) {
            // Beda dari amount_paid: perubahan status SENGAJA dibiarkan
            // tercatat normal (bukan quiet) supaya masuk activity log --
            // "status berubah dari pending ke dp" itu informasi penting
            // yang perlu ada jejaknya.
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Tentukan status baru berdasarkan progress pembayaran.
     * Return null kalau status TIDAK boleh/tidak perlu diubah otomatis.
     */
    protected function determineStatusFromPayment(int $amountPaid): ?string
    {
        // Fitur ini cuma aktif kalau tracking status DP/Lunas diaktifkan di config
        if (! config('booking.payment_status_enabled')) {
            return null;
        }

        // Jangan sentuh booking yang sudah dibatalkan atau sudah closing/lock
        if ($this->status === 'cancelled' || $this->isLocked()) {
            return null;
        }

        // Belum ada harga paket -- gak ada acuan buat dibandingin
        if (! $this->package_price || $this->package_price <= 0) {
            return null;
        }

        // Belum ada pembayaran sama sekali -- jangan diubah (biarkan admin
        // yang tentukan pending/confirmed di awal)
        if ($amountPaid <= 0) {
            return null;
        }

        return $amountPaid >= $this->package_price ? 'lunas' : 'dp';
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'amount_paid',
                'package_price',
                'gateway_status',
                'locked_at',
                'start_date',
                'end_date',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}