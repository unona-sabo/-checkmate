<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectFeature\UpdateProjectFeatureRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectFeatureController extends Controller
{
    public function store(UpdateProjectFeatureRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $feature = $project->features()->create($validated);

        return response()->json($feature, 201);
    }
}
