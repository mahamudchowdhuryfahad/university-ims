<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'invoice_number', 'reference', 'customer_id', 'warehouse_id',
        'sale_date', 'status', 'payment_status', 'payment_method',
        'subtotal', 'tax_amount', 'discount_amount', 'total_amount',
        'paid_amount', 'due_amount', 'notes', 'created_by',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function items() { return $this->hasMany(SaleItem::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
