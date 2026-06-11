<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'code', 'school_id', 'head_name', 'phone', 'email', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function school() { return $this->belongsTo(School::class); }
    public function employees() { return $this->hasMany(Employee::class); }
    public function rooms() { return $this->hasMany(Room::class); }
    public function fixedAssets() { return $this->hasMany(FixedAsset::class); } // For department-specific assets
}
