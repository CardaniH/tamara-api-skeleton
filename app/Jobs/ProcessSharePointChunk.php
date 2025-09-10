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

class ProcessSharePointChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180; // 3 minutos (aumentado para chunks grandes)
    public $tries = 3; // 3 intentos (aumentado)
    public $backoff = [30, 90, 180]; // Backoff progresivo

    public array $documentIds;
    public string $chunkKey;

    public function __construct(array $documentIds, string $chunkKey)
    {
        $this->documentIds = $documentIds;
        $this->chunkKey = $chunkKey;
    }

    public function handle(SharePointService $sharePointService)
{
    $startTime = time(); // ✅ AÑADIR: Tiempo de inicio local
    $documentCount = count($this->documentIds);
    Log::info("🔄 Procesando chunk {$this->chunkKey} con {$documentCount} documentos");

    try {
        // ✅ PROCESAR DOCUMENTOS EN MICRO-BATCHES PARA OPTIMIZAR MEMORIA
        $allDocuments = [];
        $processedCount = 0;
        $batchSize = 10; // Procesar de 10 en 10 para evitar timeouts
        
        $microBatches = array_chunk($this->documentIds, $batchSize);
        
        foreach ($microBatches as $batchIndex => $microBatch) {
            Log::info("📦 Procesando micro-batch " . ($batchIndex + 1) . "/" . count($microBatches) . " del chunk {$this->chunkKey}");
            
            $batchResult = $sharePointService->getDocumentsBatch($microBatch);
            
            if ($batchResult['success']) {
                $allDocuments = array_merge($allDocuments, $batchResult['documents']);
                $processedCount += $batchResult['processed_count'];
                
                // Pequeña pausa entre micro-batches para evitar rate limiting
                usleep(500000); // 0.5 segundos
            } else {
                Log::warning("⚠️ Micro-batch {$batchIndex} del chunk {$this->chunkKey} falló parcialmente");
            }
        }
        
        if (!empty($allDocuments)) {
            // ✅ GUARDAR CHUNK PROCESADO CON TIEMPO CORREGIDO
            $chunkData = [
                'documents' => $allDocuments,
                'processed_at' => now()->toISOString(),
                'count' => count($allDocuments),
                'requested_count' => $documentCount,
                'success_rate' => round((count($allDocuments) / $documentCount) * 100, 1),
                'processing_time_seconds' => time() - $startTime, // ✅ CORREGIDO: Usar tiempo local
                'micro_batches_processed' => count($microBatches),
            ];
            
            Cache::put("sharepoint_chunk_{$this->chunkKey}", $chunkData, 7200); // 2 horas
            
            Log::info("✅ Chunk {$this->chunkKey} procesado exitosamente:");
            Log::info("   - Documentos procesados: " . count($allDocuments) . "/{$documentCount}");
            Log::info("   - Tasa de éxito: {$chunkData['success_rate']}%");
            Log::info("   - Tiempo: " . (time() - $startTime) . " segundos");
            Log::info("   - Micro-batches: " . count($microBatches));
            
            // ✅ ACTUALIZAR PROGRESO GLOBAL
            $this->updateChunkProgress();
            
        } else {
            throw new \Exception("No se procesaron documentos en el chunk {$this->chunkKey}");
        }

    } catch (\Exception $e) {
        Log::error("❌ Error procesando chunk {$this->chunkKey}: " . $e->getMessage());
        Log::error("📍 Stack trace: " . $e->getTraceAsString());
        
        // Guardar información del error para debugging
        Cache::put("sharepoint_chunk_{$this->chunkKey}", [
            'error' => true,
            'message' => $e->getMessage(),
            'failed_at' => now()->toISOString(),
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
        ], 7200);
        
        throw $e;
    }
}


    /**
     * ✅ ACTUALIZACIÓN DE PROGRESO THREAD-SAFE
     */
    private function updateChunkProgress()
    {
        // Usar locking para evitar race conditions con múltiples workers
        $lockKey = 'sharepoint_progress_lock';
        $progressKey = 'sharepoint_chunk_progress';
        
        // Intentar obtener lock por hasta 10 segundos
        for ($i = 0; $i < 20; $i++) {
            if (Cache::add($lockKey, true, 30)) { // Lock por 30 segundos
                try {
                    $progress = Cache::get($progressKey, [
                        'completed_chunks' => 0,
                        'total_chunks' => 0,
                        'total_documents' => 0,
                        'last_update' => now()->toISOString(),
                    ]);
                    
                    $progress['completed_chunks']++;
                    $progress['last_update'] = now()->toISOString();
                    $progress['completion_percentage'] = $progress['total_chunks'] > 0 
                        ? round(($progress['completed_chunks'] / $progress['total_chunks']) * 100, 1)
                        : 0;
                    
                    Cache::put($progressKey, $progress, 3600);
                    
                    Log::info("📊 Progreso actualizado: {$progress['completed_chunks']}/{$progress['total_chunks']} chunks ({$progress['completion_percentage']}%)");
                    
                    // ✅ SI ES EL ÚLTIMO CHUNK, NOTIFICAR PARA CONSOLIDACIÓN
                    if ($progress['completed_chunks'] >= $progress['total_chunks']) {
                        Log::info("🎉 ¡Todos los chunks completados! Preparando consolidación...");
                        
                        // Marcar que todos los chunks están listos
                        Cache::put('sharepoint_all_chunks_completed', true, 1800);
                        
                        // El job de consolidación se ejecutará automáticamente
                    }
                    
                } finally {
                    Cache::forget($lockKey); // Liberar lock
                }
                break;
            } else {
                usleep(500000); // Esperar 0.5 segundos antes de reintentar
            }
        }
    }

    /**
     * ✅ MANEJO MEJORADO DE FALLOS
     */
    public function failed(\Throwable $exception)
    {
        Log::error("💥 Chunk {$this->chunkKey} falló definitivamente después de {$this->tries} intentos");
        Log::error("💥 Error: " . $exception->getMessage());
        Log::error("💥 Archivo: " . $exception->getFile() . ":" . $exception->getLine());
        
        // Marcar chunk como fallido con información detallada
        Cache::put("sharepoint_chunk_{$this->chunkKey}", [
            'error' => true,
            'final_failure' => true,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'failed_at' => now()->toISOString(),
            'total_attempts' => $this->tries,
            'document_count' => count($this->documentIds),
        ], 7200);
        
        // ✅ ACTUALIZAR PROGRESO INCLUSO CON FALLO (para no bloquear consolidación)
        $this->updateChunkProgress();
        
        // Opcional: Notificar administradores del fallo
        Log::critical("🚨 CHUNK CRÍTICO FALLIDO: {$this->chunkKey} con " . count($this->documentIds) . " documentos");
    }

    /**
     * ✅ MANEJO DE MEMORIA Y CLEANUP
     */
    public function __destruct()
    {
        // Limpiar referencias para liberar memoria
        $this->documentIds = [];
        
        // Forzar garbage collection si es necesario
        if (memory_get_usage(true) > 100 * 1024 * 1024) { // Si usa más de 100MB
            gc_collect_cycles();
        }
    }
}
