<?php

namespace App\Jobs;

use App\Services\SharePointService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchSharePointExtendedData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos máximo
    public $tries = 2; // Solo 2 reintentos para datos extendidos
    public $backoff = [60, 120];

    public function __construct()
    {
        //
    }

    public function handle(SharePointService $sharePointService)
    {
        Log::info('🔄 Iniciando fetch extendido de SharePoint...');

        try {
            // ✅ Obtener documentos recursivos (pesado)
            $allDocs = $sharePointService->getAllDocumentsRecursive(4); // Solo 4 niveles
            
            if ($allDocs['success'] && !empty($allDocs['documents'])) {
                // Filtrar documentos recientes
                $cutoffDate = now()->subDays(7);
                $recentDocs = [];
                
                // Procesar en chunks para optimizar memoria
                foreach (array_chunk($allDocs['documents'], 100) as $chunk) {
                    foreach ($chunk as $doc) {
                        $modifiedDate = \Carbon\Carbon::parse($doc['modified']);
                        if ($modifiedDate >= $cutoffDate) {
                            $recentDocs[] = $doc;
                        }
                    }
                }

                // Ordenar por fecha (más reciente primero)
                usort($recentDocs, function($a, $b) {
                    return strtotime($b['modified']) - strtotime($a['modified']);
                });

                // Estadísticas de tipos de archivo
                $documentTypes = [
                    'pdf_count' => 0,
                    'word_count' => 0,
                    'excel_count' => 0,
                    'powerpoint_count' => 0,
                    'other_count' => 0,
                ];

                foreach ($allDocs['documents'] as $doc) {
                    $ext = strtolower($doc['extension'] ?? '');
                    switch ($ext) {
                        case 'pdf':
                            $documentTypes['pdf_count']++;
                            break;
                        case 'doc':
                        case 'docx':
                            $documentTypes['word_count']++;
                            break;
                        case 'xls':
                        case 'xlsx':
                            $documentTypes['excel_count']++;
                            break;
                        case 'ppt':
                        case 'pptx':
                            $documentTypes['powerpoint_count']++;
                            break;
                        default:
                            $documentTypes['other_count']++;
                            break;
                    }
                }

                $extendedData = [
                    'recent_documents' => array_slice($recentDocs, 0, 10), // Solo 10 más recientes
                    'total_documents_count' => $allDocs['total_count'],
                    'total_recent_count' => count($recentDocs),
                    'documents_summary' => $documentTypes,
                    'last_updated' => now()->toISOString(),
                ];

                // Cache por 30 minutos
                Cache::put('sharepoint_extended_data', $extendedData, 1800);
                Log::info('✅ SharePoint datos extendidos cacheados');

                // ✅ También obtener árbol de carpetas
                $folderTree = $sharePointService->getFolderTree(3);
                if ($folderTree['success']) {
                    Cache::put('sharepoint_folder_tree', $folderTree['folders'], 3600); // 1 hora
                    Log::info('✅ SharePoint folder tree cacheado');
                }
            }

        } catch (\Exception $e) {
            Log::error('❌ Error en SharePoint extended job: ' . $e->getMessage());
            
            // No lanzar excepción - datos básicos ya están disponibles
            Cache::put('sharepoint_extended_data', [
                'recent_documents' => [],
                'total_documents_count' => 0,
                'total_recent_count' => 0,
                'documents_summary' => [
                    'pdf_count' => 0,
                    'word_count' => 0,
                    'excel_count' => 0,
                    'powerpoint_count' => 0,
                    'other_count' => 0,
                ],
                'last_updated' => now()->toISOString(),
                'error' => true,
            ], 900); // 15 minutos
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('💥 SharePoint extended job falló: ' . $exception->getMessage());
    }
}
