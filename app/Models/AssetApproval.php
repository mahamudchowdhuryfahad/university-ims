<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetApproval extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fixed_asset_id',
        'requested_by',
        'approved_by',
        'action',
        'status',
        'payload',
        'remarks',
        'approved_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'approved_at' => 'datetime',
    ];

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}