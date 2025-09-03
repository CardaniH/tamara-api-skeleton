<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- 1. IMPORTA LA CAJA DE HERRAMIENTAS
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    // --- 2. USA LAS HERRAMIENTAS DENTRO DE LA CLASE ---
    // AuthorizesRequests nos da la función authorize() que necesitamos.
    // ValidatesRequests nos da la función validate() que también estamos usando.
    use AuthorizesRequests, ValidatesRequests;
}