<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DisposalRecord extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'disposal_date', 'method', 'disposal_value',
        'disposed_to', 'reason', 'remarks', 'disposed_by',
    ];

    public function fixedAsset() { return $this->belongsTo(FixedAsset::class); }
    public function disposedBy() { return $this->belongsTo(User::class, 'disposed_by'); }
}
