<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'code', 'dean_name', 'phone', 'email', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function departments() { return $this->hasMany(Department::class); }
}
