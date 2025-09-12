<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    // ❌ ELIMINA COMPLETAMENTE ESTO:
    // public function __construct()
    // {
    //     $this->authorize('manage-structure');
    // }

    public function index()
    {
        // ✅ MUEVE LA AUTORIZACIÓN AQUÍ:
        $user = Auth::user();
        
        if (!$user || $user->role_id !== 1) {
            return response()->json(['error' => 'No tienes permisos para acceder a esta sección'], 403);
        }

        return Department::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role_id !== 1) {
            return response()->json(['error' => 'No tienes permisos'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:120|unique:departments',
        ]);

        $department = Department::create([
            'name' => $request->name,
        ]);

        return response()->json($department, 201);
    }

    public function show(Department $department)
    {
        $user = Auth::user();
        if (!$user || $user->role_id !== 1)  {
            return response()->json(['error' => 'No tienes permisos'], 403);
        }

        return response()->json($department);
    }

    public function update(Request $request, Department $department)
    {
        $user = Auth::user();
        if (!$user || $user->role_id !== 1) {
            return response()->json(['error' => 'No tienes permisos'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:120|unique:departments,name,' . $department->id,
        ]);

        $department->update([
            'name' => $request->name,
        ]);

        return response()->json($department);
    }

    public function destroy(Department $department)
    {
        $user = Auth::user();
        if (!$user || $user->role_id !== 1) {
            return response()->json(['error' => 'No tienes permisos'], 403);
        }

        $department->delete();
        return response()->json(null, 204);
    }
}
