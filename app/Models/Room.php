<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'room_number', 'building_id', 'department_id', 'floor', 'type', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function building() { return $this->belongsTo(Building::class); }
    public function department() { return $this->belongsTo(Department::class); }
}
