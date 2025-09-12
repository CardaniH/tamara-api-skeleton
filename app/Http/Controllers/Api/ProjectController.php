<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /* =========  POST /api/v1/projects  ========= */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'string|nullable',
        ]);

        $project = Project::create($data + [
            'created_by'    => Auth::id(),
            'department_id' => Auth::user()->department_id,   // o null si es Admin
        ]);

        return response()->json($project, 201);
    }

    /* =========  GET /api/v1/projects  ========= */
    public function index()
    {
        $user = Auth::user();

        // Admin global: todos
        if ($user->role_id === 1) {
            return response()->json(Project::all());
        }

        // Resto: los que él creó
        return response()->json(
            Project::where('created_by', $user->id)->get()
        );
    }

    /* =========  GET /api/v1/projects/{project}  ========= */
    public function show(Project $project)
    {
        return response()->json($project);
    }

    /* =========  PUT /api/v1/projects/{project}  ========= */
    public function update(Request $request, Project $project)
    {
        $project->update(
            $request->validate([
                'name'        => 'string',
                'description' => 'string|nullable',
            ])
        );

        return response()->json($project);
    }

    /* =========  DELETE /api/v1/projects/{project}  ========= */
    public function destroy(Project $project)
    {
        $project->delete();
        return response()->json(['deleted'=>true]);
    }
}

