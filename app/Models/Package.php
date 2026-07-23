<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    protected $fillable = ['name', 'slug', 'duration_value', 'duration_unit', 'driver_fee', 'sort_order'];

    protected $casts = [
        'driver_fee' => 'integer',
    ];

    public function carPackages()
    {
        return $this->hasMany(CarPackage::class);
    }

    /* biaya supir sesuai paket, kalau admin belum isi null, default nya dari settingss */

    public function getEffectiveDriverFeeAttribute(): int
    {
        return $this->driver_fee ?? (int) Setting::get('driver_surcharge', 0);
    }
}