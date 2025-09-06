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
                // Estadísticas básicas por rol
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
                
                // Datos para dashboard
                'all_users_with_details' => $this->getAllUsersWithRelations(),
                'departments_summary' => $this->getDepartmentsSummary(),
                'my_tasks' => $this->getMyTasks($user),
                'events_today' => $this->getEventsToday($user),
                'my_documents' => $this->getMyDocuments($user),
                'pending_tasks' => $this->getPendingTasks($user),
                'department_team_count' => $this->getDepartmentTeamCount($user),
                'my_department_files' => $this->getMyDepartmentFiles($user),
                
                // Datos específicos para DirectorDash
                'department_projects' => $this->getDepartmentProjects($user),
                'active_projects' => $this->getActiveProjects($user),
                'department_pending_tasks' => $this->getDepartmentPendingTasks($user),
                'completed_tasks' => $this->getCompletedTasks($user),
                'delivered_projects' => $this->getDeliveredProjects($user),
                'department_subdepartments' => $this->getDepartmentSubdepartments($user),
                'department_activities' => $this->getDepartmentActivities($user),
                
                // Datos del sistema
                'activity_today' => $this->getActivityToday(),
                'sharepoint_docs' => 1254,
                'new_docs_week' => 23,
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

    private function getAllUsersWithRelations()
    {
        return User::with(['role', 'department', 'subdepartment'])
                   ->get()
                   ->map(function($user) {
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

    private function getMyTasks($user)
    {
        if (!$user) return 0;
        if ($user->department_id) {
            return User::where('department_id', $user->department_id)->count() * 2;
        }
        return 0;
    }

    private function getEventsToday($user)
    {
        if (!$user) return 0;
        switch ($user->role_id) {
            case 1: return 5;
            case 2: return 4;
            case 3: return 3;
            case 4: return 2;
            default: return 1;
        }
    }

    private function getMyDocuments($user)
    {
        if (!$user) return 0;
        if ($user->department_id) {
            $deptUsers = User::where('department_id', $user->department_id)->count();
            return $deptUsers * 3;
        }
        return 5;
    }

    private function getPendingTasks($user)
    {
        if (!$user) return 0;
        if ($user->department_id) {
            return User::where('department_id', $user->department_id)->count();
        }
        return 0;
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
        
        $teamCount = User::where('department_id', $user->department_id)->count();
        return [
            'recent_files' => $teamCount * 2,
            'shared_documents' => $teamCount * 4,
            'department_folders' => max(3, $teamCount)
        ];
    }

    private function getDepartmentProjects($user)
    {
        if (!$user->department_id) return 0;
        $teamSize = User::where('department_id', $user->department_id)->count();
        return max(1, intval($teamSize / 2));
    }

    private function getActiveProjects($user)
    {
        if (!$user->department_id) return 0;
        $totalProjects = $this->getDepartmentProjects($user);
        return max(1, intval($totalProjects * 0.7));
    }

    private function getDepartmentPendingTasks($user)
    {
        if (!$user->department_id) return 0;
        $teamSize = User::where('department_id', $user->department_id)->count();
        return $teamSize * 3;
    }

    private function getCompletedTasks($user)
    {
        if (!$user->department_id) return 0;
        $teamSize = User::where('department_id', $user->department_id)->count();
        return $teamSize * 8;
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
                           ->map(function($subdept) {
                               $employeeCount = User::where('subdepartment_id', $subdept->id)->count();
                               return [
                                   'name' => $subdept->name,
                                   'employees' => $employeeCount,
                                   'projects' => max(1, intval($employeeCount / 3)),
                                   'status' => $employeeCount > 0 ? 'activo' : 'inactivo'
                               ];
                           })
                           ->toArray();
    }

    private function getDepartmentActivities($user)
    {
        if (!$user->department_id) return [];
        
        $deptName = $user->department->name ?? 'Departamento';
        $teamCount = User::where('department_id', $user->department_id)->count();
        
        return [
            [
                'icon' => '✅',
                'action' => 'Tareas completadas por el equipo',
                'user' => "Equipo $deptName",
                'time' => 'Hace 1 hora'
            ],
            [
                'icon' => '📋',
                'action' => 'Proyecto actualizado',
                'user' => 'Director',
                'time' => 'Hace 2 horas'
            ],
            [
                'icon' => '👥',
                'action' => "Reunión de equipo ($teamCount participantes)",
                'user' => $user->name,
                'time' => 'Hace 4 horas'
            ]
        ];
    }

    private function getActivityToday()
    {
        $usersToday = User::whereDate('updated_at', today())->count();
        $totalUsers = User::count();
        
        if ($totalUsers == 0) return 0;
        return intval(($usersToday / $totalUsers) * 100);
    }
}
