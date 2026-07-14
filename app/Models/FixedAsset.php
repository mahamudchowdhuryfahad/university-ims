<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;
protected $appends = ['current_value', 'accumulated_depreciation', 'years_in_use'];
protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
        'depreciation_rate' => 'decimal:4',
    ];

    protected $fillable = [
        'asset_tag', 'name', 'serial_number', 'model',
        'asset_category_id', 'brand_id', 'supplier_id', 'department_id',
        'room_id', 'employee_id', 'purchase_date', 'purchase_cost','depreciation_rate',
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


    // ── Depreciation Calculation (Reducing Balance Method) ──
    public function getYearsInUseAttribute(): float
    {
        if (!$this->purchase_date) return 0;
        return max(0, $this->purchase_date->diffInDays(now()) / 365);
    }

    public function getCurrentValueAttribute(): float
    {
        if (!$this->purchase_cost || $this->status === 'disposed') {
            return $this->purchase_cost ?? 0;
        }
        $rate = $this->depreciation_rate ?? 0.20;
        $years = $this->years_in_use;
        return round($this->purchase_cost * pow(1 - $rate, $years), 2);
    }

    public function getAccumulatedDepreciationAttribute(): float
    {
        if (!$this->purchase_cost) return 0;
        return round($this->purchase_cost - $this->current_value, 2);
    }
}
