<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\Subdepartment;
use App\Services\SharePointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private $sharePointService;

    public function __construct(SharePointService $sharePointService)
    {
        $this->sharePointService = $sharePointService;
    }

    /**
     * ✅ MÉTODO PRINCIPAL: Stats del dashboard con SharePoint real
     */
    public function stats()
    {
        try {
            $user = auth()->user()->load(['role', 'department', 'subdepartment']);

            // ✅ OBTENER DATOS REALES DE SHAREPOINT DESDE CACHE
            $sharePointData = $this->getSharePointData();

            $stats = [
                // Estadísticas básicas - DATOS REALES
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

                // Datos generales del dashboard
                'all_users_with_details' => $this->getAllUsersWithRelations(),
                'departments_summary' => $this->getDepartmentsSummary(),
                'department_team_count' => $this->getDepartmentTeamCount($user),
                
                // Campos temporalmente en 0
                'my_tasks' => 0,
                'events_today' => 0,
                'my_documents' => 0,
                'pending_tasks' => 0,

                // Archivos departamentales
                'my_department_files' => $this->getMyDepartmentFiles($user),

                // Datos específicos para Director
                'department_projects' => $this->getDepartmentProjects($user),
                'active_projects' => $this->getActiveProjects($user),
                'department_pending_tasks' => 0,
                'completed_tasks' => 0,
                'delivered_projects' => $this->getDeliveredProjects($user),
                'department_subdepartments' => $this->getDepartmentSubdepartments($user),
                'department_activities' => $this->getDepartmentActivities($user),

                // Datos específicos para Jefe
                'subdepartment_team_count' => $this->getSubdepartmentTeamCount($user),
                'subdepartment_projects' => $this->getSubdepartmentProjects($user),
                'subdepartment_pending_tasks' => 0,
                'subdepartment_files' => $this->getSubdepartmentFiles($user),
                'subdepartment_activities' => $this->getSubdepartmentActivities($user),

                // Auditoría / Control Interno
                'users_without_department' => $this->getUsersWithoutDepartment(),
                'users_without_subdepartment' => $this->getUsersWithoutSubdepartment(),
                'empty_departments_count' => Department::doesntHave('users')->count(),
                'empty_subdepartments_count' => Subdepartment::doesntHave('users')->count(),
                'empty_departments_list' => $this->getEmptyDepartmentsList(),
                'empty_subdepartments_list' => $this->getEmptySubdepartmentsList(),
                'policy_flags' => $this->getPolicyFlags(),

                // Datos específicos para Prestador
                'prestador_protocols' => $this->getPrestadorProtocols($user),
                'prestador_documents' => $this->getPrestadorDocuments($user),
                'prestador_pending_signatures' => $this->getPrestadorPendingSignatures($user),
                'prestador_activities' => $this->getPrestadorActivities($user),

                // Actividades recientes del sistema
                'recent_activities' => $this->getRecentActivitiesReal(),

                // Datos del sistema
                'activity_today' => $this->getActivityToday(),
                
                // ✅ SHAREPOINT - DATOS REALES DESDE CACHE
                'sharepoint_docs' => $sharePointData['sharepoint_docs'],
                'new_docs_week' => $sharePointData['new_docs_week'],
                'sharepoint_site_name' => $sharePointData['sharepoint_site_name'],
                'sharepoint_last_sync' => $sharePointData['sharepoint_last_sync'],
                'sharepoint_loading' => $sharePointData['loading'],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Stats detalladas de SharePoint con información de chunks
     */
    public function getDetailedSharePointStats()
    {
        try {
            $basicStats = Cache::get('sharepoint_basic_stats');
            $extendedData = Cache::get('sharepoint_extended_data');
            $chunkProgress = Cache::get('sharepoint_chunk_progress');
            
            // ✅ INFORMACIÓN DETALLADA DE CHUNKS
            $chunksInfo = $this->getChunksDetailedInfo();
            
            return response()->json([
                'success' => true,
                'data' => [
                    // Datos básicos
                    'sharepoint_docs' => $basicStats['sharepoint_docs'] ?? 0,
                    'sharepoint_site_name' => $basicStats['sharepoint_site_name'] ?? 'SharePoint',
                    'last_updated' => $basicStats['last_updated'] ?? null,
                    
                    // Estado de procesamiento
                    'loading' => $basicStats['chunk_processing'] ?? false,
                    'consolidation_completed' => $basicStats['consolidation_completed'] ?? false,
                    
                    // ✅ CHUNK PROGRESS DETALLADO
                    'chunk_progress' => [
                        'completed_chunks' => $chunkProgress['completed_chunks'] ?? 0,
                        'total_chunks' => $chunkProgress['total_chunks'] ?? 0,
                        'percentage' => $chunkProgress['completion_percentage'] ?? 0,
                        'last_update' => $chunkProgress['last_update'] ?? null,
                    ],
                    
                    // ✅ DETALLES DE CHUNKS INDIVIDUALES
                    'chunks_detail' => $chunksInfo,
                    
                    // Datos extendidos
                    'documents_summary' => $extendedData['documents_summary'] ?? [],
                    'recent_documents' => array_slice($extendedData['recent_documents'] ?? [], 0, 5),
                    'recent_documents_count' => count($extendedData['recent_documents'] ?? []),
                    'total_documents_count' => $extendedData['total_documents_count'] ?? ($basicStats['sharepoint_docs'] ?? 0),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Endpoint para forzar refresh manual de SharePoint
     */
    public function refreshSharePoint()
    {
        try {
            // Limpiar cache existente
            Cache::forget('sharepoint_basic_stats');
            Cache::forget('sharepoint_extended_data');
            Cache::forget('sharepoint_chunk_progress');
            
            // Limpiar chunks individuales
            for ($i = 1; $i <= 20; $i++) {
                Cache::forget("sharepoint_chunk_chunk_{$i}");
            }
            
            // Disparar job de fetch completo
            \App\Jobs\FetchSharePointData::dispatch();
            
            return response()->json([
                'success' => true,
                'message' => 'Actualización de SharePoint iniciada en background',
                'estimated_time' => '3-5 minutos',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar actualización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MÉTODO OPTIMIZADO: Obtener datos de SharePoint desde cache
     */
    private function getSharePointData()
    {
        try {
            // ✅ LEER DATOS REALES DESDE CACHE
            $basicStats = Cache::get('sharepoint_basic_stats');
            $extendedData = Cache::get('sharepoint_extended_data');
            $progress = Cache::get('sharepoint_chunk_progress');

            if (!$basicStats) {
                // Si no hay datos, disparar job y mostrar estado de carga
                \App\Jobs\FetchSharePointData::dispatch();
                
                return [
                    'sharepoint_docs' => 0,
                    'sharepoint_site_name' => 'SharePoint (cargando...)',
                    'sharepoint_last_sync' => null,
                    'new_docs_week' => 0,
                    'loading' => true,
                    'message' => 'Iniciando carga de documentos...',
                ];
            }

            // ✅ VERIFICAR SI AÚN SE ESTÁN PROCESANDO CHUNKS
            $isProcessing = $basicStats['chunk_processing'] ?? false;
            $consolidationCompleted = $basicStats['consolidation_completed'] ?? false;

            return [
                // Datos básicos (siempre disponibles)
                'sharepoint_docs' => $basicStats['sharepoint_docs'],
                'sharepoint_site_name' => $basicStats['sharepoint_site_name'],
                'sharepoint_last_sync' => $basicStats['sharepoint_last_sync'],
                'last_updated' => $basicStats['last_updated'],
                'new_docs_week' => $basicStats['new_docs_week'] ?? 0,
                
                // Estado de procesamiento
                'loading' => $isProcessing && !$consolidationCompleted,
                'chunk_processing' => $isProcessing,
                'consolidation_completed' => $consolidationCompleted,
                
                // Progreso si está procesando
                'progress' => $progress ? [
                    'completed_chunks' => $progress['completed_chunks'],
                    'total_chunks' => $progress['total_chunks'],
                    'percentage' => round(($progress['completed_chunks'] / max($progress['total_chunks'], 1)) * 100, 1)
                ] : null,
                
                // Datos adicionales
                'recent_documents' => array_slice($extendedData['recent_documents'] ?? [], 0, 3),
                'documents_summary' => $extendedData['documents_summary'] ?? [],
            ];
            
        } catch (\Exception $e) {
            Log::error('Dashboard SharePoint error: ' . $e->getMessage());
            
            return [
                'sharepoint_docs' => 0,
                'sharepoint_site_name' => 'SharePoint (error)',
                'sharepoint_last_sync' => null,
                'new_docs_week' => 0,
                'loading' => false,
                'error' => true,
            ];
        }
    }

    /**
     * ✅ MÉTODO PRIVADO: Información detallada de cada chunk
     */
    private function getChunksDetailedInfo()
    {
        $chunkProgress = Cache::get('sharepoint_chunk_progress');
        
        if (!$chunkProgress || !isset($chunkProgress['total_chunks'])) {
            return [];
        }
        
        $chunksDetail = [];
        
        for ($i = 1; $i <= $chunkProgress['total_chunks']; $i++) {
            $chunkData = Cache::get("sharepoint_chunk_chunk_{$i}");
            
            if ($chunkData) {
                $chunksDetail[] = [
                    'chunk_number' => $i,
                    'status' => $chunkData['error'] ?? false ? 'failed' : 'completed',
                    'documents_processed' => $chunkData['count'] ?? 0,
                    'success_rate' => $chunkData['success_rate'] ?? 0,
                    'processing_time' => $chunkData['processing_time_seconds'] ?? null,
                    'processed_at' => $chunkData['processed_at'] ?? null,
                    'error_message' => $chunkData['message'] ?? null,
                ];
            } else {
                $chunksDetail[] = [
                    'chunk_number' => $i,
                    'status' => 'pending',
                    'documents_processed' => 0,
                    'success_rate' => 0,
                    'processing_time' => null,
                    'processed_at' => null,
                    'error_message' => null,
                ];
            }
        }
        
        return $chunksDetail;
    }

    // ===== TODOS TUS MÉTODOS EXISTENTES (mantener igual) =====
    
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
        return max(0, intval($teamSize / 3));
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
        
        return [
            'recent_files' => 0,
            'shared_documents' => 0,
            'subdepartment_folders' => 0,
        ];
    }

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

    private function getPrestadorProtocols($user)
    {
        if (!$user) return 0;
        
        if ($user->department_id) {
            $deptUsers = User::where('department_id', $user->department_id)->count();
            return max(0, intval($deptUsers / 4));
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
}
