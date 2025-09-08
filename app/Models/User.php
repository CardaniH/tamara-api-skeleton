<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'department_id',
        'subdepartment_id',
        'position'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ← CAMBIO: Usar propiedad $casts en lugar de función casts()
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role_id' => 'integer',
        'department_id' => 'integer',
        'subdepartment_id' => 'integer',
    ];

    // ← TODAS LAS RELACIONES NECESARIAS
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

   public function department()
{
    return $this->belongsTo(\App\Models\Department::class);
}

public function subdepartment()
{
    return $this->belongsTo(\App\Models\Subdepartment::class);
}
}
