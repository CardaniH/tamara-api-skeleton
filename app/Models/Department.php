<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    // ← RELACIÓN QUE FALTA
    public function users()
    {
        return $this->hasMany(\App\Models\User::class, 'department_id');
    }

    // Opcional: si usas subdepartamentos
    public function subdepartments()
    {
        return $this->hasMany(\App\Models\Subdepartment::class, 'department_id');
    }
}
