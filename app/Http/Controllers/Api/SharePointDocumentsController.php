<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SharePointDocumentsController extends Controller
{
    /**
     * ✅ Listar documentos con paginación y filtros
     */
    public function index(Request $request)
    {
        try {
            // Obtener todos los documentos del cache de chunks
            $allDocuments = $this->getAllDocumentsFromChunks();
            
            if (empty($allDocuments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay documentos disponibles. Los chunks aún se están procesando.',
                    'data' => [],
                    'total' => 0,
                ]);
            }
            
            // Aplicar filtros
            $filtered = $this->applyFilters($allDocuments, $request);
            
            // Paginación
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 20);
            $total = count($filtered);
            
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($filtered, $offset, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $paginated,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                    'has_more' => ($page * $perPage) < $total
                ],
                'filters' => $this->getAppliedFilters($request),
                'last_sync' => $this->getLastSyncTime(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('SharePoint documents error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ✅ Buscar documentos por término
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'La búsqueda debe tener al menos 2 caracteres'
            ], 422);
        }
        
        try {
            $allDocuments = $this->getAllDocumentsFromChunks();
            
            if (empty($allDocuments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay documentos disponibles para buscar',
                    'data' => []
                ]);
            }
            
            // Buscar en nombre, ruta y modificado por
            $results = collect($allDocuments)->filter(function($doc) use ($query) {
                return stripos($doc['name'], $query) !== false ||
                       stripos($doc['folderPath'] ?? '', $query) !== false ||
                       stripos($doc['modifiedBy'] ?? '', $query) !== false;
            })->take(50)->values()->all();
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'query' => $query,
                'total_found' => count($results),
                'limited_to' => 50,
            ]);
            
        } catch (\Exception $e) {
            Log::error('SharePoint search error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ✅ Obtener documento específico por ID
     */
    public function show($id)
    {
        try {
            $allDocuments = $this->getAllDocumentsFromChunks();
            
            $document = collect($allDocuments)->firstWhere('id', $id);
            
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $document
            ]);
            
        } catch (\Exception $e) {
            Log::error('SharePoint document show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el documento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ✅ Obtener estadísticas de documentos
     */
    public function stats()
    {
        try {
            $allDocuments = $this->getAllDocumentsFromChunks();
            
            if (empty($allDocuments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay documentos procesados',
                    'stats' => []
                ]);
            }
            
            // Calcular estadísticas
            $stats = [
                'total_documents' => count($allDocuments),
                'by_extension' => $this->getStatsByExtension($allDocuments),
                'by_size_range' => $this->getStatsBySizeRange($allDocuments),
                'recent_documents' => $this->getRecentDocuments($allDocuments),
                'largest_documents' => $this->getLargestDocuments($allDocuments),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // ===== MÉTODOS PRIVADOS =====
    
    /**
     * 🔧 Obtener todos los documentos de los chunks en cache
     */
    private function getAllDocumentsFromChunks()
    {
        $documents = [];
        
        // Buscar hasta 30 chunks (por si acaso hay más)
        for ($i = 1; $i <= 30; $i++) {
            $chunkData = Cache::get("sharepoint_chunk_chunk_{$i}");
            
            if ($chunkData && isset($chunkData['documents']) && !($chunkData['error'] ?? false)) {
                $documents = array_merge($documents, $chunkData['documents']);
            }
        }
        
        // Ordenar por fecha de modificación (más reciente primero)
        usort($documents, function($a, $b) {
            $dateA = strtotime($a['modified'] ?? '1970-01-01');
            $dateB = strtotime($b['modified'] ?? '1970-01-01');
            return $dateB - $dateA;
        });
        
        return $documents;
    }
    
    /**
     * 🔧 Aplicar filtros a los documentos
     */
    private function applyFilters($documents, $request)
    {
        $filtered = collect($documents);
        
        // Filtro por tipo de archivo
        if ($request->has('type') && $request->get('type') !== '') {
            $type = strtolower($request->get('type'));
            $filtered = $filtered->filter(function($doc) use ($type) {
                return strtolower($doc['extension'] ?? '') === $type;
            });
        }
        
        // Filtro por fecha desde
        if ($request->has('date_from') && $request->get('date_from') !== '') {
            $dateFrom = $request->get('date_from');
            $filtered = $filtered->filter(function($doc) use ($dateFrom) {
                return ($doc['modified'] ?? '') >= $dateFrom;
            });
        }
        
        // Filtro por fecha hasta
        if ($request->has('date_to') && $request->get('date_to') !== '') {
            $dateTo = $request->get('date_to');
            $filtered = $filtered->filter(function($doc) use ($dateTo) {
                return ($doc['modified'] ?? '') <= $dateTo;
            });
        }
        
        // Filtro por tamaño mínimo
        if ($request->has('min_size') && $request->get('min_size') !== '') {
            $minSize = (int) $request->get('min_size');
            $filtered = $filtered->filter(function($doc) use ($minSize) {
                return ($doc['size'] ?? 0) >= $minSize;
            });
        }
        
        // Filtro por tamaño máximo
        if ($request->has('max_size') && $request->get('max_size') !== '') {
            $maxSize = (int) $request->get('max_size');
            $filtered = $filtered->filter(function($doc) use ($maxSize) {
                return ($doc['size'] ?? 0) <= $maxSize;
            });
        }
        
        // Filtro por carpeta
        if ($request->has('folder') && $request->get('folder') !== '') {
            $folder = $request->get('folder');
            $filtered = $filtered->filter(function($doc) use ($folder) {
                return stripos($doc['folderPath'] ?? '', $folder) !== false;
            });
        }
        
        return $filtered->values()->all();
    }
    
    /**
     * 🔧 Obtener filtros aplicados
     */
    private function getAppliedFilters($request)
    {
        return [
            'type' => $request->get('type'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'min_size' => $request->get('min_size'),
            'max_size' => $request->get('max_size'),
            'folder' => $request->get('folder'),
        ];
    }
    
    /**
     * 🔧 Obtener tiempo de última sincronización
     */
    private function getLastSyncTime()
    {
        $basicStats = Cache::get('sharepoint_basic_stats');
        return $basicStats['last_updated'] ?? null;
    }
    
    /**
     * 🔧 Estadísticas por extensión
     */
    private function getStatsByExtension($documents)
    {
        $stats = [];
        foreach ($documents as $doc) {
            $ext = strtolower($doc['extension'] ?? 'unknown');
            $stats[$ext] = ($stats[$ext] ?? 0) + 1;
        }
        arsort($stats);
        return $stats;
    }
    
    /**
     * 🔧 Estadísticas por rango de tamaño
     */
    private function getStatsBySizeRange($documents)
    {
        $ranges = [
            'small' => 0,    // < 1MB
            'medium' => 0,   // 1MB - 10MB
            'large' => 0,    // 10MB - 100MB
            'xlarge' => 0,   // > 100MB
        ];
        
        foreach ($documents as $doc) {
            $size = $doc['size'] ?? 0;
            if ($size < 1024*1024) {
                $ranges['small']++;
            } elseif ($size < 10*1024*1024) {
                $ranges['medium']++;
            } elseif ($size < 100*1024*1024) {
                $ranges['large']++;
            } else {
                $ranges['xlarge']++;
            }
        }
        
        return $ranges;
    }
    
    /**
     * 🔧 Documentos más recientes
     */
    private function getRecentDocuments($documents)
    {
        return array_slice($documents, 0, 10);
    }
    
    /**
     * 🔧 Documentos más grandes
     */
    private function getLargestDocuments($documents)
    {
        $sorted = collect($documents)->sortByDesc('size')->take(10);
        return $sorted->values()->all();
    }
}