<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'employee_id', 'email', 'phone', 'designation', 'department_id', 'status', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function department() { return $this->belongsTo(Department::class); }
}
