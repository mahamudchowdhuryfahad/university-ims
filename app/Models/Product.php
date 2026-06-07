<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'name', 'slug', 'sku', 'barcode', 'category_id', 'brand_id',
        'description', 'cost_price', 'selling_price', 'tax_rate',
        'unit', 'image', 'alert_quantity', 'is_active', 'status', 'created_by',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function stock() { return $this->hasMany(Stock::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }

    public function getTotalStockAttribute(): int
    {
        return $this->stock()->sum('quantity');
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->total_stock <= $this->alert_quantity;
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : asset('images/no-product.png');
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%");
        });
    }
    public function scopeLowStock($query)
    {
        return $query->whereHas('stock', function ($q) {
            $q->whereColumn('quantity', '<=', 'products.alert_quantity');
        });
    }
}
