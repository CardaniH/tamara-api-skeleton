<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SharePointService;
use Illuminate\Http\Request;

class SharePointController extends Controller
{
    private $sharePointService;

    public function __construct(SharePointService $sharePointService)
    {
        $this->sharePointService = $sharePointService;
    }

    /**
     * Listar archivos de SharePoint
     */
    public function files(Request $request)
    {
        $folderPath = $request->get('folder', '');
        $result = $this->sharePointService->listFiles($folderPath);
        
        return response()->json($result);
    }

    /**
     * Obtener archivo específico
     */
    public function file($id)
    {
        $result = $this->sharePointService->getFile($id);
        
        return response()->json($result);
    }

    /**
     * Estadísticas de SharePoint
     */
    public function stats()
    {
        $result = $this->sharePointService->getStats();
        
        return response()->json($result);
    }
}
