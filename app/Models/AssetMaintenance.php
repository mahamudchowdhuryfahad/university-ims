<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssetMaintenance extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'supplier_id', 'type', 'maintenance_date',
        'completion_date', 'cost', 'status', 'description', 'remarks', 'created_by',
    ];

    public function fixedAsset() { return $this->belongsTo(FixedAsset::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
