<?php
/* app/Http/Controllers/Api/TaskController.php */
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Task, Project};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /* ───── listar ──── */
    public function index(Project $project)
{
    // Cargar relaciones de usuario asignado y creador
    $tasks = $project->tasks()
        ->with(['assignedUser:id,name,email', 'creator:id,name,email'])
        ->get();
    
    return response()->json($tasks);
}

    /* ───── crear ──── */
    public function store(Request $r, Project $project)
    {
        // regla mínima: solo el admin global (rol 1) o el creador del proyecto
        $u = Auth::user();
        if ($u->role_id !== 1 && $project->created_by !== $u->id) {
            return response()->json(['message'=>'Prohibido'], 403);
        }

        $data = $r->validate([
            'title' => 'required|string|max:255',
            'type'  => 'required|in:personal,departmental,subdepartmental',
            'priority' => 'in:low,medium,high,urgent',
            'due_date' => 'date|nullable',
            'assigned_to' => 'exists:users,id|nullable'
        ]);

        $task = Task::create($data + [
        'project_id' => $project->id,
        'created_by' => $u->id,
        'status'     => 'not_started'
    ]);
        $task->load(['assignedUser:id,name,email', 'creator:id,name,email']);
        return response()->json($task, 201);
    }

    /* ───── actualizar ──── */
    public function update(Request $r, Task $task)
    {
        // admin global, creador o responsable pueden editar
        $u = Auth::user();
        if ($u->role_id !== 1 && $task->created_by !== $u->id && $task->assigned_to !== $u->id) {
            return response()->json(['message'=>'Prohibido'], 403);
        }

        $task->update($r->validate([
            'title' => 'string',
            'status'=> 'in:not_started,in_progress,completed,cancelled',
            'priority' => 'in:low,medium,high,urgent',
            'due_date' => 'date|nullable',
            'assigned_to' => 'exists:users,id|nullable'
        ]));    
        $task->load(['assignedUser:id,name,email', 'creator:id,name,email']);

        return response()->json($task);
    }

    /* ───── eliminar ──── */
    public function destroy(Task $task)
    {
        $u = Auth::user();
        if ($u->role_id !== 1 && $task->created_by !== $u->id) {
            return response()->json(['message'=>'Prohibido'], 403);
        }

        $task->delete();
        return response()->json(['deleted'=>true]);
    }
}
