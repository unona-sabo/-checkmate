<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectFeatureController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'module' => 'nullable|array',
            'module.*' => 'string|max:100',
            'priority' => 'required|in:critical,high,medium,low',
        ]);

        $feature = $project->features()->create($validated);

        return response()->json($feature, 201);
    }
}
