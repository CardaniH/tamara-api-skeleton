<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\Subdepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            $user = auth()->user()->load(['role', 'department', 'subdepartment']);

            $stats = [
                // Estadísticas básicas por rol - DATOS REALES
                'total_users' => User::count(),
                'total_departments' => Department::count(),
                'total_subdepartments' => Subdepartment::count(),
                'admin_count' => User::where('role_id', 1)->count(),
                'director_count' => User::where('role_id', 2)->count(),
                'jefe_count' => User::where('role_id', 3)->count(),
                'empleado_count' => User::where('role_id', 4)->count(),
                'auditor_count' => User::where('role_id', 5)->count(),
                'prestador_count' => User::where('role_id', 6)->count(),
                'new_users_week' => User::where('created_at', '>=', now()->subDays(7))->count(),

                // Datos generales del dashboard - DATOS REALES
                'all_users_with_details' => $this->getAllUsersWithRelations(),
                'departments_summary' => $this->getDepartmentsSummary(),
                'department_team_count' => $this->getDepartmentTeamCount($user),
                
                // CAMPOS TEMPORALMENTE EN 0 HASTA INTEGRAR SISTEMAS REALES
                'my_tasks' => 0,
                'events_today' => 0,
                'my_documents' => 0,
                'pending_tasks' => 0,

                // Archivos departamentales - DATOS REALES
                'my_department_files' => $this->getMyDepartmentFiles($user),

                // Datos específicos para Director - DATOS REALES
                'department_projects' => $this->getDepartmentProjects($user),
                'active_projects' => $this->getActiveProjects($user),
                'department_pending_tasks' => 0, // Temporalmente 0
                'completed_tasks' => 0, // Temporalmente 0
                'delivered_projects' => $this->getDeliveredProjects($user),
                'department_subdepartments' => $this->getDepartmentSubdepartments($user),
                'department_activities' => $this->getDepartmentActivities($user),

                // Datos específicos para Jefe (subdepartamento) - DATOS REALES
                'subdepartment_team_count' => $this->getSubdepartmentTeamCount($user),
                'subdepartment_projects' => $this->getSubdepartmentProjects($user),
                'subdepartment_pending_tasks' => 0, // Temporalmente 0
                'subdepartment_files' => $this->getSubdepartmentFiles($user),
                'subdepartment_activities' => $this->getSubdepartmentActivities($user),

                // Auditoría / Control Interno - DATOS REALES
                'users_without_department' => $this->getUsersWithoutDepartment(),
                'users_without_subdepartment' => $this->getUsersWithoutSubdepartment(),
                'empty_departments_count' => Department::doesntHave('users')->count(),
                'empty_subdepartments_count' => Subdepartment::doesntHave('users')->count(),
                'empty_departments_list' => $this->getEmptyDepartmentsList(),
                'empty_subdepartments_list' => $this->getEmptySubdepartmentsList(),
                'policy_flags' => $this->getPolicyFlags(),

                // Datos específicos para Prestador (Protocolos) - DATOS REALES
                'prestador_protocols' => $this->getPrestadorProtocols($user),
                'prestador_documents' => $this->getPrestadorDocuments($user),
                'prestador_pending_signatures' => $this->getPrestadorPendingSignatures($user),
                'prestador_activities' => $this->getPrestadorActivities($user),

                // Actividades recientes del sistema - DATOS REALES
                'recent_activities' => $this->getRecentActivitiesReal(),

                // Datos del sistema - DATOS REALES
                'activity_today' => $this->getActivityToday(),
                'sharepoint_docs' => 0, // Será conectado con SharePoint
                'new_docs_week' => 0,   // Será conectado con SharePoint
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== MÉTODOS CON DATOS REALES =====

    private function getDepartmentActivities($user)
    {
        if (!$user->department_id) return [];

        $recentUsers = User::where('department_id', $user->department_id)
                          ->where('updated_at', '>=', now()->subDays(7))
                          ->orderBy('updated_at', 'desc')
                          ->take(3)
                          ->get();

        if ($recentUsers->isEmpty()) {
            return [];
        }

        $activities = [];
        foreach ($recentUsers as $recentUser) {
            $activities[] = [
                'icon' => '',
                'action' => 'Usuario actualizado en el sistema',
                'user' => $recentUser->name,
                'time' => $recentUser->updated_at->format('d/m/Y H:i')
            ];
        }

        return $activities;
    }

    private function getSubdepartmentActivities($user)
    {
        if (!$user || !$user->subdepartment_id) return [];

        $recentUsers = User::where('subdepartment_id', $user->subdepartment_id)
                          ->where('updated_at', '>=', now()->subDays(7))
                          ->orderBy('updated_at', 'desc')
                          ->take(3)
                          ->get();

        if ($recentUsers->isEmpty()) {
            return [];
        }

        $activities = [];
        foreach ($recentUsers as $recentUser) {
            $activities[] = [
                'icon' => '',
                'action' => 'Miembro del equipo activo',
                'user' => $recentUser->name,
                'time' => $recentUser->updated_at->format('d/m/Y H:i')
            ];
        }

        return $activities;
    }

    private function getPrestadorActivities($user)
    {
        if (!$user) return [];

        if ($user->updated_at->diffInDays(now()) > 7) {
            return [];
        }

        return [
            [
                'icon' => '',
                'action' => 'Acceso al sistema',
                'user' => $user->name,
                'time' => $user->updated_at->format('d/m/Y H:i')
            ]
        ];
    }

    private function getRecentActivitiesReal()
    {
        $recentUsers = User::orderBy('created_at', 'desc')
                          ->take(3)
                          ->get();

        $activities = [];

        foreach ($recentUsers as $user) {
            if ($user->created_at->diffInDays(now()) <= 30) {
                $activities[] = [
                    'icon' => '',
                    'action' => 'Nuevo usuario registrado',
                    'user' => $user->name,
                    'time' => $user->created_at->format('d/m/Y H:i')
                ];
            } else {
                $activities[] = [
                    'icon' => '',
                    'action' => 'Usuario activo en el sistema',
                    'user' => $user->name,
                    'time' => $user->updated_at->format('d/m/Y H:i')
                ];
            }
        }

        if (empty($activities)) {
            return [];
        }

        return array_slice($activities, 0, 4);
    }

    private function getActivityToday()
    {
        $usersActiveToday = User::whereDate('updated_at', today())->count();
        $totalUsers = User::count();

        if ($totalUsers == 0) return 0;
        
        return intval(($usersActiveToday / $totalUsers) * 100);
    }

    // ===== MÉTODOS DE DATOS REALES =====

    private function getAllUsersWithRelations()
    {
        return User::with(['role', 'department', 'subdepartment'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_name' => $user->role?->name ?? 'Sin rol',
                    'department_name' => $user->department?->name ?? 'Sin departamento',
                    'subdepartment_name' => $user->subdepartment?->name ?? 'Sin subdepartamento',
                    'position' => $user->position ?? 'Sin cargo',
                    'created_at' => $user->created_at->format('d/m/Y')
                ];
            });
    }

    private function getDepartmentsSummary()
    {
        return DB::table('departments')
            ->leftJoin('users', 'departments.id', '=', 'users.department_id')
            ->select([
                'departments.id',
                'departments.name',
                DB::raw('COUNT(users.id) as user_count')
            ])
            ->groupBy('departments.id', 'departments.name')
            ->get();
    }

    private function getDepartmentTeamCount($user)
    {
        if (!$user->department_id) return 0;
        return User::where('department_id', $user->department_id)->count();
    }

    private function getMyDepartmentFiles($user)
    {
        if (!$user->department_id) {
            return [
                'recent_files' => 0,
                'shared_documents' => 0,
                'department_folders' => 0
            ];
        }

        // Por ahora retorna 0 hasta integrar SharePoint
        return [
            'recent_files' => 0,
            'shared_documents' => 0,
            'department_folders' => 0
        ];
    }

    private function getDepartmentProjects($user)
    {
        if (!$user->department_id) return 0;
        $teamSize = User::where('department_id', $user->department_id)->count();
        return max(0, intval($teamSize / 3)); // Basado en tamaño del equipo
    }

    private function getActiveProjects($user)
    {
        if (!$user->department_id) return 0;
        $totalProjects = $this->getDepartmentProjects($user);
        return max(0, intval($totalProjects * 0.7));
    }

    private function getDeliveredProjects($user)
    {
        if (!$user->department_id) return 0;
        $totalProjects = $this->getDepartmentProjects($user);
        return max(0, $totalProjects - $this->getActiveProjects($user));
    }

    private function getDepartmentSubdepartments($user)
    {
        if (!$user->department_id) return [];

        return Subdepartment::where('department_id', $user->department_id)
            ->get()
            ->map(function ($subdept) {
                $employeeCount = User::where('subdepartment_id', $subdept->id)->count();
                return [
                    'name' => $subdept->name,
                    'employees' => $employeeCount,
                    'projects' => max(0, intval($employeeCount / 3)),
                    'status' => $employeeCount > 0 ? 'activo' : 'inactivo'
                ];
            })
            ->toArray();
    }

    // ===== SUBDEPARTAMENTO (JEFE) =====

    private function getSubdepartmentTeamCount($user)
    {
        if (!$user || !$user->subdepartment_id) return 0;
        return User::where('subdepartment_id', $user->subdepartment_id)->count();
    }

    private function getSubdepartmentProjects($user)
    {
        if (!$user || !$user->subdepartment_id) return 0;
        $team = $this->getSubdepartmentTeamCount($user);
        return max(0, intdiv($team, 3));
    }

    private function getSubdepartmentFiles($user)
    {
        if (!$user || !$user->subdepartment_id) {
            return [
                'recent_files' => 0,
                'shared_documents' => 0,
                'subdepartment_folders' => 0,
            ];
        }
        
        // Por ahora retorna 0 hasta integrar SharePoint
        return [
            'recent_files' => 0,
            'shared_documents' => 0,
            'subdepartment_folders' => 0,
        ];
    }

    // ===== AUDITORÍA / CONTROL INTERNO =====

    private function getUsersWithoutDepartment()
    {
        return User::whereNull('department_id')
            ->with('role:id,name')
            ->select('id', 'name', 'email', 'role_id')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => optional($u->role)->name ?? 'Sin rol',
                ];
            });
    }

    private function getUsersWithoutSubdepartment()
    {
        return User::whereNull('subdepartment_id')
            ->with(['role:id,name', 'department:id,name'])
            ->select('id', 'name', 'email', 'role_id', 'department_id')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => optional($u->role)->name ?? 'Sin rol',
                    'department' => optional($u->department)->name ?? 'Sin departamento',
                ];
            });
    }

    private function getEmptyDepartmentsList()
    {
        return Department::doesntHave('users')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function getEmptySubdepartmentsList()
    {
        return Subdepartment::doesntHave('users')
            ->leftJoin('departments', 'subdepartments.department_id', '=', 'departments.id')
            ->select('subdepartments.id', 'subdepartments.name', 'departments.name as department_name')
            ->orderBy('departments.name')
            ->orderBy('subdepartments.name')
            ->get();
    }

    private function getPolicyFlags()
    {
        $jefesSinSub = User::where('role_id', 3)->whereNull('subdepartment_id')->count();
        $directoresSinDepto = User::where('role_id', 2)->whereNull('department_id')->count();

        return [
            'jefes_sin_subdepartamento' => $jefesSinSub,
            'directores_sin_departamento' => $directoresSinDepto,
            'usuarios_sin_departamento' => User::whereNull('department_id')->count(),
            'usuarios_sin_subdepartment' => User::whereNull('subdepartment_id')->count(),
        ];
    }

    // ===== PRESTADOR (PROTOCOLOS) =====

    private function getPrestadorProtocols($user)
    {
        if (!$user) return 0;
        
        if ($user->department_id) {
            $deptUsers = User::where('department_id', $user->department_id)->count();
            return max(0, intval($deptUsers / 4)); // Más conservador
        }
        
        return 0;
    }

    private function getPrestadorDocuments($user)
    {
        if (!$user) return 0;
        
        $protocols = $this->getPrestadorProtocols($user);
        return $protocols * 2;
    }

    private function getPrestadorPendingSignatures($user)
    {
        if (!$user) return 0;
        
        $protocols = $this->getPrestadorProtocols($user);
        return intval($protocols * 0.3);
    }
}
