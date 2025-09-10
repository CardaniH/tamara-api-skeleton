<?php

namespace App\Console\Commands;

use App\Jobs\FetchSharePointData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class InitializeSharePoint extends Command
{
    protected $signature = 'sharepoint:init {--force : Forzar inicialización aunque haya datos en cache}';
    protected $description = 'Inicializar datos de SharePoint en background';

    public function handle()
    {
        $force = $this->option('force');
        
        // Verificar si ya hay datos en cache
        if (!$force && Cache::has('sharepoint_basic_stats')) {
            $this->info('⏭️ Datos de SharePoint ya existen en cache');
            $this->info('💡 Usa --force para forzar actualización');
            return 0;
        }

        if ($force) {
            $this->info('🗑️ Limpiando cache existente...');
            Cache::forget('sharepoint_basic_stats');
            Cache::forget('sharepoint_extended_data');
            Cache::forget('sharepoint_folder_tree');
        }

        $this->info('🚀 Iniciando carga inicial de SharePoint...');
        
        // Poner estado de loading en cache
        Cache::put('sharepoint_basic_stats', [
            'sharepoint_docs' => 0,
            'new_docs_week' => 0,
            'sharepoint_site_name' => 'SharePoint (cargando...)',
            'sharepoint_last_sync' => now()->toISOString(),
            'last_updated' => now()->toISOString(),
            'loading' => true,
        ], 600);

        FetchSharePointData::dispatch();
        
        $this->info('✅ Job de SharePoint enviado a la cola');
        $this->info('💡 Ejecuta "php artisan queue:work" para procesar');
        $this->info('📊 O usa "php artisan horizon" para monitoring avanzado');
        
        return 0;
    }
}
