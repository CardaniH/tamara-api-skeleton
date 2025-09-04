<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\SubdepartmentController; // <-- AÑADIR ESTA LÍNEA

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Rutas existentes
    Route::apiResource('departments', DepartmentController::class);
    
    // NUEVA RUTA - Añadir esta línea
    Route::apiResource('subdepartments', SubdepartmentController::class);
});
