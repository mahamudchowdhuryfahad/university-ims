<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id', 'product_id', 'requested_quantity',
        'approved_quantity', 'fulfilled_quantity', 'unit', 'remarks',
    ];

    public function requisition() { return $this->belongsTo(Requisition::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
