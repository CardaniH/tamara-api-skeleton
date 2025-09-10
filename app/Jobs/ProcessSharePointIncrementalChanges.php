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

class ProcessSharePointIncrementalChanges implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60; // 1 minuto (más rápido)
    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function handle(SharePointService $sharePointService)
    {
        Log::info('🔄 Iniciando sync incremental de SharePoint...');

        try {
            // Obtener solo cambios desde última sincronización
            $changes = $sharePointService->getIncrementalChanges();
            
            if (!$changes['success']) {
                throw new \Exception('Error obteniendo cambios incrementales: ' . $changes['error']);
            }
            
            $totalChanges = $changes['total_changes'];
            
            if ($totalChanges === 0) {
                Log::info('✅ No hay cambios nuevos en SharePoint');
                return;
            }
            
            Log::info("📊 Cambios detectados:");
            Log::info("   - Nuevos: " . count($changes['changes']['new_documents']));
            Log::info("   - Modificados: " . count($changes['changes']['modified_documents']));
            Log::info("   - Eliminados: " . count($changes['changes']['deleted_documents']));
            
            // Actualizar cache con cambios
            $updateResult = $sharePointService->updateCacheWithChanges($changes['changes']);
            
            if ($updateResult['success']) {
                Log::info("✅ Cache actualizado con {$totalChanges} cambios en <30 segundos");
                
                // Marcar timestamp de última sincronización incremental
                Cache::put('sharepoint_last_incremental_check', now()->toISOString(), 86400);
                
            } else {
                throw new \Exception('Error actualizando cache: ' . $updateResult['error']);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Error en sync incremental: ' . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('💥 Sync incremental falló: ' . $exception->getMessage());
    }
}
