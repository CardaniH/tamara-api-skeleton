<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdepartment extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'department_id'];

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class, 'subdepartment_id');
    }
}
