<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio optimizado para SharePoint Online mediante Microsoft Graph API
 * 
 * Funcionalidades principales:
 * - Autenticación con Azure AD
 * - Estadísticas de documentos recursivas (sin límites)
 * - Referencias de documentos optimizadas
 * - Paginación completa automática
 */
class SharePointService
{
    private Client $client;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $siteId;

    public function __construct()
    {
        $this->client = new Client();
        $this->tenantId = config('microsoft.tenant_id');
        $this->clientId = config('microsoft.client_id');
        $this->clientSecret = config('microsoft.client_secret');
        $this->siteId = config('microsoft.sharepoint_site_id');
    }

    /**
     * ✅ OPTIMIZADO: Token con caché
     */
    private function getAccessToken(): string
    {
        return Cache::remember('sharepoint_access_token', 3500, function () {
            try {
                $response = $this->client->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'form_params' => [
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => 'https://graph.microsoft.com/.default',
                        'grant_type' => 'client_credentials',
                    ]
                ]);

                $data = json_decode($response->getBody(), true);
                
                if (!isset($data['access_token'])) {
                    throw new \Exception('No access token received');
                }

                return $data['access_token'];
                
            } catch (RequestException $e) {
                Log::error('SharePoint token error', ['error' => $e->getMessage()]);
                throw new \Exception('Failed to get SharePoint access token: ' . $e->getMessage());
            }
        });
    }

    /**
     * ✅ OPTIMIZADO: Llamadas Graph API con timeouts
     */
    private function makeGraphRequest(string $endpoint, string $method = 'GET', ?array $data = null): array
    {
        try {
            $token = $this->getAccessToken();
            $url = "https://graph.microsoft.com/v1.0{$endpoint}";

            $options = [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 15,        // 15 segundos máximo
                'connect_timeout' => 5, // 5 segundos para conectar
            ];

            if ($data && $method !== 'GET') {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $url, $options);
            return json_decode($response->getBody(), true);
            
        } catch (RequestException $e) {
            Log::error("SharePoint API error [{$endpoint}]: " . $e->getMessage());
            throw new \Exception('SharePoint API call failed: ' . $e->getMessage());
        }
    }

    /**
     * ✅ BÁSICO: Información del sitio (para jobs)
     */
    public function getBasicSiteInfo(): array
    {
        try {
            $siteInfo = $this->makeGraphRequest("/sites/{$this->siteId}");
            
            return [
                'success' => true,
                'site_name' => $siteInfo['displayName'] ?? 'SharePoint',
                'last_modified' => $siteInfo['lastModifiedDateTime'] ?? now()->toISOString(),
                'id' => $siteInfo['id'] ?? $this->siteId,
            ];
            
        } catch (\Exception $e) {
            Log::error('SharePoint getBasicSiteInfo error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'site_name' => 'SharePoint (error)',
                'last_modified' => now()->toISOString(),
            ];
        }
    }

    /**
     * ✅ SIN LÍMITES: Referencias de documentos recursivas completas
     */
    public function getDocumentReferences(int $maxDepth = 50): array
    {
        try {
            $references = [];
            $this->collectDocumentReferences("/sites/{$this->siteId}/drive/root/children", $references, 0, $maxDepth);
            
            return [
                'success' => true,
                'references' => $references,
                'total_count' => count($references)
            ];
            
        } catch (\Exception $e) {
            Log::error('SharePoint getDocumentReferences error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'references' => []
            ];
        }
    }

    /**
     * ✅ SIN LÍMITES: Recolección recursiva completa con paginación total
     */
    private function collectDocumentReferences(string $endpoint, array &$references, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth >= $maxDepth) return;
        
        try {
            $response = $this->makeGraphRequest($endpoint);
            
            // ✅ PROCESAR TODOS LOS ELEMENTOS DE LA PÁGINA ACTUAL
            foreach ($response['value'] ?? [] as $item) {
                if (isset($item['folder'])) {
                    // Es carpeta - explorar recursivamente
                    $folderEndpoint = "/sites/{$this->siteId}/drive/items/{$item['id']}/children";
                    $this->collectDocumentReferences($folderEndpoint, $references, $currentDepth + 1, $maxDepth);
                    
                } elseif (isset($item['file'])) {
                    // Es archivo - guardar referencia básica
                    $references[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'size' => $item['size'],
                        'modified' => $item['lastModifiedDateTime'],
                        'parent_id' => $item['parentReference']['id'] ?? null,
                        'depth' => $currentDepth,
                    ];
                }
            }
            
            // ✅ MANEJAR PAGINACIÓN COMPLETA - NO PERDER NINGÚN DOCUMENTO
            while (isset($response['@odata.nextLink'])) {
                $nextUrl = str_replace('https://graph.microsoft.com/v1.0', '', $response['@odata.nextLink']);
                Log::info("📄 Procesando página siguiente: " . substr($nextUrl, 0, 100) . '...');
                
                $response = $this->makeGraphRequest($nextUrl);
                
                foreach ($response['value'] ?? [] as $item) {
                    if (isset($item['folder'])) {
                        $folderEndpoint = "/sites/{$this->siteId}/drive/items/{$item['id']}/children";
                        $this->collectDocumentReferences($folderEndpoint, $references, $currentDepth + 1, $maxDepth);
                    } elseif (isset($item['file'])) {
                        $references[] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'size' => $item['size'],
                            'modified' => $item['lastModifiedDateTime'],
                            'parent_id' => $item['parentReference']['id'] ?? null,
                            'depth' => $currentDepth,
                        ];
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error collecting references from {$endpoint}: " . $e->getMessage());
        }
    }

    /**
     * ✅ SIN LÍMITES: Estadísticas con exploración completa optimizada
     */
    public function getStats(): array
    {
        try {
            // Información básica del sitio
            $siteInfo = $this->getBasicSiteInfo();
            
            if (!$siteInfo['success']) {
                return [
                    'success' => false,
                    'stats' => [
                        'sharepoint_docs' => 0,
                        'new_docs_week' => 0,
                        'site_name' => 'SharePoint (Error)',
                        'last_modified' => now()->toISOString(),
                        'max_depth_scanned' => 0,
                    ]
                ];
            }

            // ✅ CONTEO COMPLETO SIN LÍMITES - USAR REFERENCIAS OPTIMIZADAS
            $allDocsResponse = $this->getDocumentReferences(50); // 50 niveles máximo
            $totalFiles = $allDocsResponse['success'] ? $allDocsResponse['total_count'] : 0;
            
            // Calcular archivos recientes de las referencias (más eficiente)
            $recentFiles = 0;
            if ($allDocsResponse['success']) {
                $weekAgo = now()->subDays(7);
                foreach ($allDocsResponse['references'] as $file) {
                    if (isset($file['modified']) && \Carbon\Carbon::parse($file['modified']) >= $weekAgo) {
                        $recentFiles++;
                    }
                }
            }

            return [
                'success' => true,
                'stats' => [
                    'sharepoint_docs' => $totalFiles, // ← TODOS LOS DOCUMENTOS SIN LÍMITES
                    'new_docs_week' => $recentFiles,
                    'site_name' => $siteInfo['site_name'],
                    'last_modified' => $siteInfo['last_modified'],
                    'max_depth_scanned' => 50,
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('SharePoint getStats error: ' . $e->getMessage());
            return [
                'success' => false,
                'stats' => [
                    'sharepoint_docs' => 0,
                    'new_docs_week' => 0,
                    'site_name' => 'SharePoint (Error)',
                    'last_modified' => now()->toISOString(),
                    'max_depth_scanned' => 0,
                ]
            ];
        }
    }

    /**
     * ✅ BATCH: Obtener detalles completos de documentos específicos
     */
    public function getDocumentsBatch(array $documentIds): array
    {
        $details = [];
        
        foreach (array_chunk($documentIds, 20) as $batch) { // Procesar en lotes de 20
            foreach ($batch as $docId) {
                try {
                    $response = $this->makeGraphRequest("/sites/{$this->siteId}/drive/items/{$docId}");
                    
                    if (isset($response['file'])) {
                        $details[] = [
                            'id' => $response['id'],
                            'name' => $response['name'],
                            'size' => $response['size'],
                            'type' => $response['file']['mimeType'] ?? 'unknown',
                            'extension' => pathinfo($response['name'], PATHINFO_EXTENSION),
                            'modified' => $response['lastModifiedDateTime'],
                            'created' => $response['createdDateTime'],
                            'url' => $response['webUrl'],
                            'downloadUrl' => $response['@microsoft.graph.downloadUrl'] ?? null,
                            'modifiedBy' => $response['lastModifiedBy']['user']['displayName'] ?? 'Sistema',
                            'createdBy' => $response['createdBy']['user']['displayName'] ?? 'Sistema',
                        ];
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Error getting document details for {$docId}: " . $e->getMessage());
                    // Continuar con el siguiente documento
                }
            }
        }
        
        return [
            'success' => true,
            'documents' => $details,
            'processed_count' => count($details)
        ];
    }

    /**
     * ✅ DOCUMENTOS RECIENTES: Basado en referencias (más eficiente)
     */
    public function getRecentDocuments(int $days = 7, ?int $limit = null): array
    {
        try {
            // Obtener todas las referencias primero
            $referencesResponse = $this->getDocumentReferences(10); // Solo 10 niveles para ser rápido
            
            if (!$referencesResponse['success']) {
                return $referencesResponse;
            }
            
            $cutoffDate = now()->subDays($days);
            $recentRefs = [];
            
            // Filtrar referencias por fecha
            foreach ($referencesResponse['references'] as $ref) {
                $modifiedDate = \Carbon\Carbon::parse($ref['modified']);
                if ($modifiedDate >= $cutoffDate) {
                    $recentRefs[] = $ref;
                }
            }
            
            // Ordenar por fecha (más reciente primero)
            usort($recentRefs, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
            
            // Aplicar límite si se especifica
            $finalRefs = $limit ? array_slice($recentRefs, 0, $limit) : $recentRefs;
            
            return [
                'success' => true,
                'documents' => $finalRefs, // Solo referencias básicas
                'total_recent' => count($recentRefs)
            ];
            
        } catch (\Exception $e) {
            Log::error('SharePoint getRecentDocuments error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'documents' => []
            ];
        }
    }
    
}