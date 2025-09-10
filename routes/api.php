<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\SubdepartmentController; // <-- AÑADIR ESTA LÍNEA
use App\Http\Controllers\Api\DashboardController;
// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
    $user = $request->user()->load(['role', 'department', 'subdepartment']);
    
    return response()->json([
        'success' => true,
        'user' => $user
    ]);
});
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Rutas existentes
    Route::apiResource('departments', DepartmentController::class);
    
    // NUEVA RUTA - Añadir esta línea
    Route::apiResource('subdepartments', SubdepartmentController::class);

    
     Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
      Route::prefix('sharepoint')->group(function () {
        Route::get('/files', [App\Http\Controllers\Api\SharePointController::class, 'files']);
        Route::get('/file/{id}', [App\Http\Controllers\Api\SharePointController::class, 'file']);
        Route::get('/stats', [App\Http\Controllers\Api\SharePointController::class, 'stats']);
    });
     Route::prefix('sharepoint')->group(function () {
        Route::get('/documents', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'index']);
        Route::get('/documents/search', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'search']);
        Route::get('/documents/stats', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'stats']);
        Route::get('/documents/{id}', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'show']);
    });
    

   
});
