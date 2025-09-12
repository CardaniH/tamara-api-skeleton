<?php
/* database/migrations/2025_09_12_000000_add_projects_tasks_tables.php */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ───── 1. Projects ───────────────────────── */
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            /* quién lo crea (FK a users) */
            $t->foreignId('created_by')
               ->constrained('users')
               ->cascadeOnDelete();
            /* opcional: a qué departamento pertenece */
            $t->foreignId('department_id')
               ->nullable()
               ->constrained('departments')
               ->nullOnDelete();
            $t->timestamps();
        });

        /* ───── 2. Tasks ──────────────────────────── */
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();

            /* contenido principal */
            $t->string('title');
            $t->longText('description')->nullable();

            /* clasificación de visibilidad */
            $t->enum('type', ['personal','departmental','subdepartmental']);

            /* estado general */
            $t->enum('status', [
                'not_started','in_progress','completed','cancelled'
            ])->default('not_started');

            $t->enum('priority', [
                'low','medium','high','urgent'
            ])->default('medium');

            $t->date('due_date')->nullable();

            /* relaciones */
            $t->foreignId('project_id')
               ->constrained('projects')
               ->cascadeOnDelete();

            $t->foreignId('created_by')      // autor
               ->constrained('users')
               ->cascadeOnDelete();

            $t->foreignId('assigned_to')     // responsable
               ->nullable()
               ->constrained('users')
               ->nullOnDelete();

            $t->foreignId('department_id')   // para tasks departamentales
               ->nullable()
               ->constrained('departments')
               ->nullOnDelete();

            $t->foreignId('subdepartment_id')// para tasks sub-departamentales
               ->nullable()
               ->constrained('subdepartments')
               ->nullOnDelete();

            /* auditoría automática */
            $t->timestamp('last_updated_at')->nullable(); // marca manual al editar
            $t->foreignId('last_updated_by')              // quién fue el último
               ->nullable()
               ->constrained('users')
               ->nullOnDelete();

            $t->timestamps();

            /* índices útiles */
            $t->index(['type','status']);
            $t->index(['department_id','status']);
            $t->index(['subdepartment_id','status']);
            $t->index(['assigned_to','status']);
        });

        /* ───── 3. Task Logs ──────────────────────── */
        Schema::create('task_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')
               ->constrained('tasks')
               ->cascadeOnDelete();
            $t->foreignId('user_id')           // quién hizo la acción
               ->constrained('users');
            $t->string('field_changed',50);    // status, priority, assigned_to…
            $t->text('old_value')->nullable();
            $t->text('new_value')->nullable();
            $t->timestamp('created_at')->useCurrent();
            /* visibilidad del log (public, department, subdepartment, private) */
            $t->enum('visibility', [
                'public','department','subdepartment','private'
            ])->default('public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_logs');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
