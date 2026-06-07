<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'code', 'address', 'city', 'country', 'phone', 'email', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function stock() { return $this->hasMany(Stock::class); }
}
