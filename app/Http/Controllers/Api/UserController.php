<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Mapeo de roles específicos de tu sistema
    private $roles = [
        1 => 'Admin Global',
        2 => 'Director de Departamento', 
        3 => 'Jefe de Subdepartamento',
        4 => 'Empleado',
        5 => 'Auditor/Control Interno',
        6 => 'Prestador (Protocolos)'
    ];

    private $rolePermissions = [
        1 => ['access_all', 'manage_users', 'manage_departments', 'manage_subdepartments', 'audit_access'],
        2 => ['manage_department', 'view_department_users', 'manage_department_files'],
        3 => ['manage_subdepartment', 'view_subdepartment_users', 'manage_subdepartment_files'],
        4 => ['view_assigned_files', 'upload_files', 'view_own_tasks'],
        5 => ['audit_read_all', 'generate_reports', 'view_all_activities'],
        6 => ['read_protocols', 'read_documents']
    ];

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $roleFilter = $request->get('role_filter', '');
            $departmentFilter = $request->get('department_filter', '');

            $currentUser = auth()->user();
            
            $query = User::query()
                ->when($search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                })
                ->when($roleFilter, function ($query, $roleFilter) {
                    $query->where('role_id', $roleFilter);
                })
                ->when($departmentFilter, function ($query, $departmentFilter) {
                    $query->where('department_id', $departmentFilter);
                })
                ->with(['department', 'subdepartment']);

            // Filtrar según el rol del usuario actual
            switch ($currentUser->role_id) {
                case 1: // Admin Global - Ve todos
                    break;
                case 2: // Director - Solo su departamento
                    $query->where('department_id', $currentUser->department_id);
                    break;
                case 3: // Jefe - Solo su subdepartamento
                    $query->where('subdepartment_id', $currentUser->subdepartment_id);
                    break;
                case 5: // Auditor - Ve todos para auditoría
                    break;
                default: // Otros roles no pueden gestionar usuarios
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para gestionar usuarios'
                    ], 403);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'roles' => $this->roles,
                'current_user_permissions' => $this->rolePermissions[$currentUser->role_id] ?? []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function stats()
    {
        try {
            $currentUser = auth()->user();
            
            // Estadísticas base
            $baseQuery = User::query();
            
            // Filtrar según rol del usuario actual
            switch ($currentUser->role_id) {
                case 1: // Admin Global
                    break;
                case 2: // Director
                    $baseQuery->where('department_id', $currentUser->department_id);
                    break;
                case 3: // Jefe
                    $baseQuery->where('subdepartment_id', $currentUser->subdepartment_id);
                    break;
                case 5: // Auditor
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para ver estadísticas'
                    ], 403);
            }

            $stats = [
                'total_users' => $baseQuery->count(),
                'admin_global' => $baseQuery->where('role_id', 1)->count(),
                'directores' => $baseQuery->where('role_id', 2)->count(),
                'jefes' => $baseQuery->where('role_id', 3)->count(),
                'empleados' => $baseQuery->where('role_id', 4)->count(),
                'auditores' => $baseQuery->where('role_id', 5)->count(),
                'prestadores' => $baseQuery->where('role_id', 6)->count(),
                'recent_users' => $baseQuery->where('created_at', '>=', now()->subDays(7))->count(),
                'active_today' => $baseQuery->where('last_login_at', '>=', now()->startOfDay())->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'user_role' => $currentUser->role_id,
                'role_name' => $this->roles[$currentUser->role_id] ?? 'Desconocido'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $currentUser = auth()->user();
            $user = User::findOrFail($id);
            
            // Verificar permisos para editar usuario
            if (!$this->canEditUser($currentUser, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para editar este usuario'
                ], 403);
            }
            
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'department_id' => 'sometimes|exists:departments,id',
                'subdepartment_id' => 'sometimes|exists:subdepartments,id',
                'role_id' => 'sometimes|in:1,2,3,4,5,6',
            ]);

            $user->update($request->only(['name', 'email', 'department_id', 'subdepartment_id', 'role_id']));

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente',
                'data' => $user->load(['department', 'subdepartment'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    private function canEditUser($currentUser, $targetUser)
    {
        switch ($currentUser->role_id) {
            case 1: // Admin Global - Puede editar todos
                return true;
            case 2: // Director - Solo usuarios de su departamento
                return $targetUser->department_id === $currentUser->department_id;
            case 3: // Jefe - Solo usuarios de su subdepartamento
                return $targetUser->subdepartment_id === $currentUser->subdepartment_id;
            default:
                return false;
        }
    }

    public function getRoles()
    {
        return response()->json([
            'success' => true,
            'data' => $this->roles
        ]);
    }
}
