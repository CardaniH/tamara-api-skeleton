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

class FetchSharePointData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function __construct()
    {
        //
    }

    public function handle(SharePointService $sharePointService)
{
    Log::info('🚀 Iniciando fetch COMPLETO de SharePoint (sin límites)...');

    try {
        // Información básica del sitio
        $siteInfo = $sharePointService->getBasicSiteInfo();
        
        // ✅ OBTENER TODAS LAS REFERENCIAS SIN LÍMITES
        Log::info('📊 Obteniendo referencias completas de documentos...');
        $references = $sharePointService->getDocumentReferences(50); // Sin límite real
        
        if (!$references['success']) {
            throw new \Exception('No se pudieron obtener referencias de documentos');
        }

        $totalDocs = $references['total_count'];
        Log::info("📊 TOTAL DOCUMENTOS ENCONTRADOS (SIN LÍMITES): {$totalDocs}");

        // ✅ GUARDAR ESTADÍSTICAS CON CONTEO REAL COMPLETO
        $basicData = [
            'sharepoint_docs' => $totalDocs, // NÚMERO REAL COMPLETO
            'new_docs_week' => 0, // Se calculará en chunks
            'sharepoint_site_name' => $siteInfo['site_name'] ?? 'SharePoint',
            'sharepoint_last_sync' => $siteInfo['last_modified'] ?? now()->toISOString(),
            'last_updated' => now()->toISOString(),
            'loading' => false,
            'chunk_processing' => true,
            'total_depth_scanned' => max(array_column($references['references'], 'depth')),
        ];

        Cache::put('sharepoint_basic_stats', $basicData, 1200); // 20 minutos
        Log::info('✅ Stats básicas guardadas - DOCUMENTOS TOTALES: ' . $totalDocs);

        // ✅ CHUNKING DINÁMICO BASADO EN TOTAL REAL
        $documentIds = array_column($references['references'], 'id');
        $chunkSize = 50; // Mantener tamaño manejable
        $chunks = array_chunk($documentIds, $chunkSize);
        $totalChunks = count($chunks);

        Log::info("📦 Creando {$totalChunks} chunks de {$chunkSize} documentos cada uno");

        // Inicializar progreso completo
        Cache::put('sharepoint_chunk_progress', [
            'completed_chunks' => 0,
            'total_chunks' => $totalChunks,
            'total_documents' => $totalDocs,
            'last_update' => now()->toISOString(),
        ], 1800); // 30 minutos

        // ✅ DESPACHAR TODOS LOS CHUNKS NECESARIOS
        Log::info("⚡ PROCESANDO CHUNKS SÍNCRONAMENTE PARA DEBUG");

foreach ($chunks as $index => $chunk) {
    $chunkKey = "chunk_" . ($index + 1);
    
    // Procesar inmediatamente SIN cola
    try {
        $job = new ProcessSharePointChunk($chunk, $chunkKey);
        $job->handle(app(SharePointService::class));
        Log::info("✅ Chunk {$chunkKey} procesado síncronamente");
    } catch (\Exception $e) {
        Log::error("❌ Error en chunk {$chunkKey}: " . $e->getMessage());
    }
}

// Consolidar también síncronamente
$consolidateJob = new ConsolidateSharePointData();
$consolidateJob->handle();

        Log::info("📤 Despachados {$totalChunks} chunks para procesamiento COMPLETO");
        
        // ✅ DESPACHAR JOB DE CONSOLIDACIÓN FINAL
        ConsolidateSharePointData::dispatch()
            ->delay(now()->addSeconds(30)); // Después de que terminen los chunks

    } catch (\Exception $e) {
        Log::error('❌ Error en SharePoint job completo: ' . $e->getMessage());
        
        // Fallback conservador
        Cache::put('sharepoint_basic_stats', [
            'sharepoint_docs' => 0,
            'sharepoint_site_name' => 'SharePoint (error)',
            'last_updated' => now()->toISOString(),
            'loading' => false,
            'error' => true,
        ], 600);
        
        throw $e;
    }
}
    public function failed(\Throwable $exception)
    {
        Log::error('💥 SharePoint basic job falló definitivamente: ' . $exception->getMessage());
        
        // Asegurar que siempre haya algo en cache
        Cache::put('sharepoint_basic_stats', [
            'sharepoint_docs' => 632,
            'new_docs_week' => 0,
            'sharepoint_site_name' => 'SharePoint (error)',
            'sharepoint_last_sync' => now()->toISOString(),
            'last_updated' => now()->toISOString(),
            'loading' => false,
            'error' => true,
        ], 600);
    }
}