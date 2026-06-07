<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Requisition extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'reference', 'department_id', 'requested_by', 'approved_by',
        'type', 'status', 'request_date', 'required_date',
        'purpose', 'remarks', 'approved_at',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function items() { return $this->hasMany(RequisitionItem::class); }
}
