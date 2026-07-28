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
        'with_driver',
        'driver_surcharge_price',
        'status',
        'customer_name',
        'customer_phone',
        'notes',
        'delivery_address',
        'delivery_distance_km',
        'delivery_fee_price',
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
        'driver_surcharge_price' => 'integer',
        'delivery_distance_km' => 'float',
        'delivery_fee_price' => 'integer',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'gateway_payload' => 'array',
        'locked_at' => 'datetime',
    ];


    // Total harga = harga paket + surcharge supir (kalau with_driver aktif) + biaya antar jemput
    public function getTotalPriceAttribute(): int
    {
        return ($this->package_price ?? 0)
            + ($this->with_driver ? ($this->driver_surcharge_price ?? 0) : 0)
            + ($this->delivery_fee_price ?? 0);
    }
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

 
    /* amount paid cuma acuan aja, diitung ulang tiap pembayaran baru, nominal terbayar real ada di booking payment */
    public function recalculateAmountPaid(): void
    {
        $total = $this->payments()
            ->whereIn('type', ['dp', 'pelunasan', 'penyesuaian'])
            ->sum('amount');

        $refund = $this->payments()->where('type', 'refund')->sum('amount');
        $amountPaid = max(0, $total - $refund);

        // update amount paid
        $this->updateQuietly(['amount_paid' => $amountPaid]);

        $newStatus = $this->determineStatusFromPayment($amountPaid);

        if ($newStatus !== null && $newStatus !== $this->status) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Status by pembayaran 
     * return null kalau status tidak boleh/tidak perlu diubah otomatis.
     */
    protected function determineStatusFromPayment(int $amountPaid): ?string
    {
        // Fitur cuma aktif kalau tracking status DP/Lunas diaktifkan di config
        if (! config('booking.payment_status_enabled')) {
            return null;
        }

        // Jstatys cancel sm di lock ga return apa2
        if ($this->status === 'cancelled' || $this->isLocked()) {
            return null;
        }

        // belum ada harga paket
        if (! $this->total_price || $this->total_price <= 0) {
            return null;
        }
        // belum ada pembayaran sama sekali 
        // yang tentukan pending/confirmed di awal
        if ($amountPaid <= 0) {
            return null;
        }

        return $amountPaid >= $this->total_price ? 'lunas' : 'dp';
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
                'with_driver',
                'driver_surcharge_price',
                'delivery_distance_km',
                'delivery_fee_price',
                'gateway_status',
                'locked_at',
                'start_date',
                'end_date',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}