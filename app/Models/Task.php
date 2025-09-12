<?php

/* app/Models/Task.php */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;


class Task extends Model
{
    protected $fillable = [
        'title','content','type','status','priority','due_date',
        'project_id','created_by','assigned_to',
        'department_id','subdepartment_id'
    ];

    /* relaciones */
    public function project()  { return $this->belongsTo(Project::class); }
    public function assignee() { return $this->belongsTo(User::class,'assigned_to'); }
    public function assignedUser(){return $this->belongsTo(User::class, 'assigned_to');}
    public function creator(){ return $this->belongsTo(User::class, 'created_by');}

    /* === SCOPES DE VISIBILIDAD === */
    public function scopeVisibleTo(Builder $q, $u = null): Builder
    {
        $u = $u ?: Auth::user();

        // 1  Admin Global: todo menos personales ajenas
        if ($u->role_id === 1)
            return $q->where(fn($s) => $s->where('type','!=','personal')
                                         ->orWhere('assigned_to',$u->id));

        // 5  Auditor: sin tareas personales ajenas
        if ($u->role_id === 5)
            return $q->whereIn('type',['departmental','subdepartmental'])
                     ->orWhere(fn($p)=>$p->where('type','personal')
                                         ->where('assigned_to',$u->id));

        // 2  Director
        if ($u->role_id === 2)
            return $q->where('department_id',$u->department_id)
                     ->orWhere('assigned_to',$u->id);

        // 3  Jefe Sub
        if ($u->role_id === 3)
            return $q->where('subdepartment_id',$u->subdepartment_id)
                     ->orWhere('department_id',$u->department_id)
                     ->orWhere('assigned_to',$u->id);

        // 4-6  Empleado / Prestador
        return $q->where('subdepartment_id',$u->subdepartment_id)
                 ->orWhere('department_id',$u->department_id)
                 ->orWhere('assigned_to',$u->id);
    }
    
}
