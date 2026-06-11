<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'email', 'phone', 'address', 'city', 'country', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function purchases() { return $this->hasMany(Purchase::class); }
}
