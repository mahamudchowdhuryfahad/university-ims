<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssetTransfer extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'from_department_id', 'to_department_id',
        'from_room_id', 'to_room_id', 'from_employee_id', 'to_employee_id',
        'transfer_date', 'reason', 'notes', 'status', 'transferred_by',
    ];

    public function fixedAsset() { return $this->belongsTo(FixedAsset::class); }
    public function fromDepartment() { return $this->belongsTo(Department::class, 'from_department_id'); }
    public function toDepartment() { return $this->belongsTo(Department::class, 'to_department_id'); }
    public function fromEmployee() { return $this->belongsTo(Employee::class, 'from_employee_id'); }
    public function toEmployee() { return $this->belongsTo(Employee::class, 'to_employee_id'); }
    public function transferredBy() { return $this->belongsTo(User::class, 'transferred_by'); }
}
