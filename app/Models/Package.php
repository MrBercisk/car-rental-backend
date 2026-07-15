<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    protected $fillable = ['name', 'slug', 'duration_value', 'duration_unit', 'sort_order'];

    public function carPackages()
    {
        return $this->hasMany(CarPackage::class);
    }
}
