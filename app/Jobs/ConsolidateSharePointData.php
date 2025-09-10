<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConsolidateSharePointData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos
    public $tries = 2;

    public function handle()
    {
        Log::info('🔄 Iniciando consolidación final de SharePoint...');

        try {
            $progress = Cache::get('sharepoint_chunk_progress');
            
            if (!$progress || $progress['completed_chunks'] < $progress['total_chunks']) {
                Log::info('⏳ Chunks aún procesándose, reagendando consolidación...');
                // Reagendar en 2 minutos
                ConsolidateSharePointData::dispatch()->delay(now()->addMinutes(2));
                return;
            }

            // ✅ CONSOLIDAR TODOS LOS CHUNKS PROCESADOS
            $allDocuments = [];
            $totalProcessed = 0;
            
            for ($i = 1; $i <= $progress['total_chunks']; $i++) {
                $chunkData = Cache::get("sharepoint_chunk_chunk_{$i}");
                
                if ($chunkData && isset($chunkData['documents'])) {
                    $allDocuments = array_merge($allDocuments, $chunkData['documents']);
                    $totalProcessed += count($chunkData['documents']);
                }
            }

            Log::info("📊 Consolidados {$totalProcessed} documentos de {$progress['total_chunks']} chunks");

            // ✅ ANÁLISIS DE DOCUMENTOS CONSOLIDADOS
            $recentDocs = [];
            $cutoffDate = now()->subDays(7);
            $documentTypes = [
                'pdf_count' => 0,
                'word_count' => 0,
                'excel_count' => 0,
                'powerpoint_count' => 0,
                'other_count' => 0,
            ];

            foreach ($allDocuments as $doc) {
                // Documentos recientes
                if (isset($doc['modified'])) {
                    $modifiedDate = \Carbon\Carbon::parse($doc['modified']);
                    if ($modifiedDate >= $cutoffDate) {
                        $recentDocs[] = $doc;
                    }
                }

                // Conteo por tipo
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

            // Ordenar documentos recientes
            usort($recentDocs, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });

            // ✅ ACTUALIZAR ESTADÍSTICAS BÁSICAS FINALES
            $basicStats = Cache::get('sharepoint_basic_stats', []);
            $basicStats['new_docs_week'] = count($recentDocs);
            $basicStats['chunk_processing'] = false;
            $basicStats['consolidation_completed'] = true;
            $basicStats['last_consolidation'] = now()->toISOString();

            Cache::put('sharepoint_basic_stats', $basicStats, 1800);

            // ✅ GUARDAR DATOS CONSOLIDADOS FINALES
            $consolidatedData = [
                'recent_documents' => array_slice($recentDocs, 0, 20), // Top 20
                'total_documents_count' => $totalProcessed,
                'total_recent_count' => count($recentDocs),
                'documents_summary' => $documentTypes,
                'consolidated_at' => now()->toISOString(),
                'chunks_processed' => $progress['total_chunks'],
            ];

            Cache::put('sharepoint_extended_data', $consolidatedData, 1800);
            Log::info('✅ Consolidación completada - ' . $totalProcessed . ' documentos procesados');

        } catch (\Exception $e) {
            Log::error('❌ Error en consolidación: ' . $e->getMessage());
            throw $e;
        }
    }
}