<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla de Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Tabla de Departamentos
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Tabla de Subdepartamentos
        Schema::create('subdepartments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Modificar la tabla 'users' para añadir las relaciones (llaves foráneas)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('subdepartment_id')->references('id')->on('subdepartments')->onDelete('set null');
        });
        
        // Insertar los roles iniciales en la base de datos
        DB::table('roles')->insert([
            ['name' => 'Admin Global', 'description' => 'Acceso total al sistema.'],
            ['name' => 'Director de Departamento', 'description' => 'Acceso de gestión a su departamento.'],
            ['name' => 'Jefe de Subdepartamento', 'description' => 'Acceso de gestión a su subdepartamento.'],
            ['name' => 'Empleado', 'description' => 'Acceso de operación en su subdepartamento.'],
            ['name' => 'Auditor/Control Interno', 'description' => 'Acceso de solo lectura para auditoría.'],
            ['name' => 'Prestador (Protocolos)', 'description' => 'Acceso de solo lectura a documentos.'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['subdepartment_id']);
        });
        
        Schema::dropIfExists('subdepartments');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('roles');
    }
};