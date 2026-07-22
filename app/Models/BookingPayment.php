<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'amount',
        'type',
        'paid_at',
        'method',
        'proof_path',
        'note',
        'gateway_transaction_id',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // setiap kali ada pembayaran baru dicatat (atau dihapus, meski jarang terjadi),
        // langsung sinkronkan cache amount_paid di booking induknya
        static::created(function (BookingPayment $payment) {
            $payment->booking?->recalculateAmountPaid();
        });

        static::deleted(function (BookingPayment $payment) {
            $payment->booking?->recalculateAmountPaid();
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}