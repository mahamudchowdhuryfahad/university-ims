<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_tag', 'name', 'serial_number', 'model',
        'asset_category_id', 'brand_id', 'supplier_id', 'department_id',
        'room_id', 'employee_id', 'purchase_date', 'purchase_cost',
        'warranty_expiry', 'status', 'condition', 'description',
        'image', 'created_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($asset) {
            if (!$asset->asset_tag) {
                $asset->asset_tag = 'AST-' . strtoupper(Str::random(8));
            }
        });
    }

    public function assetCategory() { return $this->belongsTo(AssetCategory::class, 'asset_category_id'); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function assignments() { return $this->hasMany(AssetAssignment::class); }
    public function transfers() { return $this->hasMany(AssetTransfer::class); }
    public function maintenances() { return $this->hasMany(AssetMaintenance::class); }
    public function disposal() { return $this->hasOne(DisposalRecord::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvals() { return $this->hasMany(AssetApproval::class); }
}