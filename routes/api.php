<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ===== IMPORTAR TODOS LOS CONTROLADORES =====
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\SubdepartmentController;
use App\Http\Controllers\Api\UserController;              // ← FALTABA ESTE
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DashboardController;

// Ruta pública de login
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    
    // Ruta de usuario autenticado
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load(['role', 'department', 'subdepartment']);
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // ===== RUTAS DE USUARIOS =====
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/stats', [UserController::class, 'stats']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::get('/roles', [UserController::class, 'getRoles']);

    // ===== RUTAS EXISTENTES =====
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('subdepartments', SubdepartmentController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.tasks', TaskController::class)->shallow();

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // SharePoint
    Route::prefix('sharepoint')->group(function () {
        Route::get('/documents', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'index']);
        Route::get('/documents/search', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'search']);
        Route::get('/documents/stats', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'stats']);
        Route::get('/documents/{id}', [App\Http\Controllers\Api\SharePointDocumentsController::class, 'show']);
    });
});