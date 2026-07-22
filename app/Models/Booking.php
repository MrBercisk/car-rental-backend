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
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->orderBy('paid_at');
    }

    /**
     * Cek tabrakan tanggal untuk product tertentu.
     * Hanya status yang "masih aktif" (bukan cancelled) yang dianggap menghalangi.
     */
    public function scopeOverlapping($query, int $productId, $start, $end, ?int $exceptId = null)
    {
        return $query->where('product_id', $productId)
            ->whereIn('status', ['pending', 'dp', 'lunas', 'confirmed'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId));
    }

    /**
     * Sumber kebenaran nominal terbayar ada di tabel booking_payments (append-only ledger).
     * amount_paid di sini hanya CACHE, dihitung ulang tiap ada pembayaran baru/koreksi.
     * Dipanggil otomatis lewat event di model BookingPayment — jangan panggil manual
     * kecuali memang perlu resync data lama.
     */
    public function recalculateAmountPaid(): void
    {
        $total = $this->payments()
            ->whereIn('type', ['dp', 'pelunasan', 'penyesuaian'])
            ->sum('amount');

        $refund = $this->payments()->where('type', 'refund')->sum('amount');

        $this->updateQuietly(['amount_paid' => max(0, $total - $refund)]);
    }

    /**
     * True kalau booking sudah "closing" — field finansial tidak boleh
     * diedit lagi lewat form Filament. Koreksi harus lewat entri baru
     * di booking_payments (type: penyesuaian/refund).
     */
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