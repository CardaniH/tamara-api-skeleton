<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Relación con Subdepartments
     */
    public function subdepartments(): HasMany
    {
        return $this->hasMany(Subdepartment::class);
    }
}
