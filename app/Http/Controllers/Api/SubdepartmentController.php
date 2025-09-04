<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subdepartment;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SubdepartmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Lista todos los subdepartamentos con sus departamentos
     */
    public function index()
    {
        $this->authorize('manage-structure'); // ✅ Autorización dentro del método
        
        return Subdepartment::with('department')->orderBy('name')->get();
    }

    /**
     * Crear un nuevo subdepartamento
     */
    public function store(Request $request)
    {
        $this->authorize('manage-structure'); // ✅ Autorización dentro del método
        
        $validated = $request->validate([
            'name' => 'required|string|max:120|unique:subdepartments',
            'department_id' => 'required|exists:departments,id'
        ]);

        return Subdepartment::create($validated);
    }

    /**
     * Mostrar un subdepartamento específico
     */
    public function show(Subdepartment $subdepartment)
    {
        $this->authorize('manage-structure'); // ✅ Autorización dentro del método
        
        return $subdepartment->load('department');
    }

    /**
     * Actualizar un subdepartamento
     */
    public function update(Request $request, Subdepartment $subdepartment)
    {
        $this->authorize('manage-structure'); // ✅ Autorización dentro del método
        
        $validated = $request->validate([
            'name' => 'required|string|max:120|unique:subdepartments,name,' . $subdepartment->id,
            'department_id' => 'required|exists:departments,id'
        ]);

        $subdepartment->update($validated);
        return $subdepartment->load('department');
    }

    /**
     * Eliminar un subdepartamento
     */
    public function destroy(Subdepartment $subdepartment)
    {
        $this->authorize('manage-structure'); // ✅ Autorización dentro del método
        
        $subdepartment->delete();
        return response()->json(['message' => 'Subdepartamento eliminado correctamente']);
    }
}
