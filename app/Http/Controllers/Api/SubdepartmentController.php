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
    public function index(Request $request)
{
    try {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $query = Subdepartment::with('department')->orderBy('name');

        // === FILTRO POR DEPARTMENT_ID ===
        
        if ($request->has('department_id') && $request->department_id) {
            $departmentId = $request->department_id;
            
            // Verificar que el departamento existe
            $departmentExists = \App\Models\Department::where('id', $departmentId)->exists();
            if (!$departmentExists) {
                return response()->json([
                    'error' => 'Departamento no encontrado'
                ], 404);
            }
            
            $query->where('department_id', $departmentId);
        }

        // === RESTRICCIONES POR ROL ===
        
        switch ($user->role_id) {
            case 1: // Admin Global - Ve todos
                break;
                
            case 2: // Director - Solo subdepartamentos de su departamento
            case 3: // Jefe - Solo subdepartamentos de su departamento
                if ($user->department_id) {
                    $query->where('department_id', $user->department_id);
                }
                break;
                
            case 5: // Auditor - Ve todos
                break;
                
            default: // Otros roles
                if ($user->department_id) {
                    $query->where('department_id', $user->department_id);
                } else {
                    // Si no tiene departamento, retorna vacío
                    return response()->json([]);
                }
        }

        $subdepartments = $query->get();

        return response()->json($subdepartments);

    } catch (\Exception $e) {
        \Log::error('Error en SubdepartmentController@index: ' . $e->getMessage());
        
        return response()->json([
            'error' => 'Error al obtener subdepartamentos',
            'message' => $e->getMessage()
        ], 500);
    }
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
