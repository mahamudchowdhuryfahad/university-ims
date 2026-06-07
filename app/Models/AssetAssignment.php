<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'employee_id', 'department_id', 'room_id',
        'assigned_date', 'return_date', 'status', 'notes', 'assigned_by',
    ];

    public function fixedAsset() { return $this->belongsTo(FixedAsset::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
}
