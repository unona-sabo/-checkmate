<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\ReorderProjectsRequest;
use App\Http\Requests\Project\SearchProjectRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->attributes->get('workspace');

        $query = $workspace
            ? $workspace->projects()
            : auth()->user()->projects();

        $projects = $query
            ->withCount(['checklists', 'testSuites', 'testRuns'])
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function reorder(ReorderProjectsRequest $request)
    {
        $validated = $request->validated();

        $workspace = $request->attributes->get('workspace');

        foreach ($validated['projects'] as $projectData) {
            $query = Project::where('id', $projectData['id']);
            if ($workspace) {
                $query->where('workspace_id', $workspace->id);
            } else {
                $query->where('user_id', auth()->id());
            }
            $query->update(['order' => $projectData['order']]);
        }

        return back()->with('success', 'Projects reordered successfully.');
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    public function store(StoreProjectRequest $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validated();

        $workspace = $request->attributes->get('workspace');

        $project = auth()->user()->projects()->create([
            ...$validated,
            'workspace_id' => $workspace?->id,
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function search(SearchProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validated();

        $term = '%'.$validated['q'].'%';
        $results = [];

        // Test Suites
        $testSuites = $project->testSuites()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->with('parent')
            ->limit(10)
            ->get();

        if ($testSuites->isNotEmpty()) {
            $results[] = [
                'type' => 'test_suites',
                'label' => 'Test Suites',
                'count' => $testSuites->count(),
                'items' => $testSuites->map(fn ($suite) => [
                    'id' => $suite->id,
                    'title' => $suite->name,
                    'subtitle' => $suite->parent ? 'in '.$suite->parent->name : null,
                    'badge' => $suite->type,
                    'url' => route('test-suites.show', [$project, $suite]),
                ]),
            ];
        }

        // Test Cases (via testSuites relationship)
        $testCases = \App\Models\TestCase::query()
            ->whereHas('testSuite', fn ($q) => $q->where('project_id', $project->id))
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('preconditions', 'like', $term)
                    ->orWhere('expected_result', 'like', $term);
            })
            ->with('testSuite')
            ->limit(10)
            ->get();

        if ($testCases->isNotEmpty()) {
            $results[] = [
                'type' => 'test_cases',
                'label' => 'Test Cases',
                'count' => $testCases->count(),
                'items' => $testCases->map(fn ($tc) => [
                    'id' => $tc->id,
                    'title' => $tc->title,
                    'subtitle' => $tc->testSuite ? 'in '.$tc->testSuite->name : null,
                    'badge' => $tc->priority,
                    'extra_badge' => $tc->type,
                    'url' => route('test-cases.show', [$project, $tc->testSuite, $tc]),
                ]),
            ];
        }

        // Checklists
        $checklists = $project->checklists()
            ->where('name', 'like', $term)
            ->limit(10)
            ->get();

        if ($checklists->isNotEmpty()) {
            $results[] = [
                'type' => 'checklists',
                'label' => 'Checklists',
                'count' => $checklists->count(),
                'items' => $checklists->map(fn ($cl) => [
                    'id' => $cl->id,
                    'title' => $cl->name,
                    'subtitle' => null,
                    'badge' => null,
                    'url' => route('checklists.show', [$project, $cl]),
                ]),
            ];
        }

        // Test Runs
        $testRuns = $project->testRuns()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('environment', 'like', $term)
                    ->orWhere('milestone', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($testRuns->isNotEmpty()) {
            $results[] = [
                'type' => 'test_runs',
                'label' => 'Test Runs',
                'count' => $testRuns->count(),
                'items' => $testRuns->map(fn ($run) => [
                    'id' => $run->id,
                    'title' => $run->name,
                    'subtitle' => $run->environment ? $run->environment : null,
                    'badge' => $run->status,
                    'url' => route('test-runs.show', [$project, $run]),
                ]),
            ];
        }

        // Bug Reports
        $bugreports = $project->bugreports()
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($bugreports->isNotEmpty()) {
            $results[] = [
                'type' => 'bugreports',
                'label' => 'Bug Reports',
                'count' => $bugreports->count(),
                'items' => $bugreports->map(fn ($bug) => [
                    'id' => $bug->id,
                    'title' => $bug->title,
                    'subtitle' => null,
                    'badge' => $bug->status,
                    'extra_badge' => $bug->severity,
                    'url' => route('bugreports.show', [$project, $bug]),
                ]),
            ];
        }

        // Documentations
        $documentations = $project->documentations()
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('content', 'like', $term)
                    ->orWhere('category', 'like', $term);
            })
            ->with('parent')
            ->limit(10)
            ->get();

        if ($documentations->isNotEmpty()) {
            $results[] = [
                'type' => 'documentations',
                'label' => 'Documentations',
                'count' => $documentations->count(),
                'items' => $documentations->map(fn ($doc) => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'subtitle' => $doc->parent ? 'in '.$doc->parent->title : null,
                    'badge' => $doc->category,
                    'url' => route('documentations.show', [$project, $doc]),
                ]),
            ];
        }

        // Releases
        $releases = $project->releases()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('version', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($releases->isNotEmpty()) {
            $results[] = [
                'type' => 'releases',
                'label' => 'Releases',
                'count' => $releases->count(),
                'items' => $releases->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->version.' — '.$r->name,
                    'subtitle' => null,
                    'badge' => $r->status,
                    'url' => route('releases.show', [$project, $r]),
                ]),
            ];
        }

        // Design Links
        $designLinks = $project->designLinks()
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('url', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($designLinks->isNotEmpty()) {
            $results[] = [
                'type' => 'design_links',
                'label' => 'Design Links',
                'count' => $designLinks->count(),
                'items' => $designLinks->map(fn ($dl) => [
                    'id' => $dl->id,
                    'title' => $dl->title,
                    'subtitle' => $dl->url,
                    'badge' => $dl->category,
                    'url' => '/projects/'.$project->id.'/design',
                ]),
            ];
        }

        // Notes
        $notes = $project->notes()
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('content', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($notes->isNotEmpty()) {
            $results[] = [
                'type' => 'notes',
                'label' => 'Notes',
                'count' => $notes->count(),
                'items' => $notes->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'subtitle' => null,
                    'badge' => $n->is_draft ? 'draft' : 'published',
                    'url' => route('projects.notes.show', [$project, $n]),
                ]),
            ];
        }

        // Test Data — Users
        $testUsers = $project->testUsers()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('role', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($testUsers->isNotEmpty()) {
            $results[] = [
                'type' => 'test_data_users',
                'label' => 'Test Data — Users',
                'count' => $testUsers->count(),
                'items' => $testUsers->map(fn ($u) => [
                    'id' => $u->id,
                    'title' => $u->name,
                    'subtitle' => $u->email,
                    'badge' => $u->role,
                    'url' => '/projects/'.$project->id.'/test-data',
                ]),
            ];
        }

        // Test Data — Commands
        $testCommands = $project->testCommands()
            ->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhere('command', 'like', $term)
                    ->orWhere('comment', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($testCommands->isNotEmpty()) {
            $results[] = [
                'type' => 'test_data_commands',
                'label' => 'Test Data — Commands',
                'count' => $testCommands->count(),
                'items' => $testCommands->map(fn ($c) => [
                    'id' => $c->id,
                    'title' => $c->description,
                    'subtitle' => $c->command,
                    'badge' => $c->category,
                    'url' => '/projects/'.$project->id.'/test-data',
                ]),
            ];
        }

        // Test Data — Links
        $testLinks = $project->testLinks()
            ->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhere('url', 'like', $term)
                    ->orWhere('comment', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($testLinks->isNotEmpty()) {
            $results[] = [
                'type' => 'test_data_links',
                'label' => 'Test Data — Links',
                'count' => $testLinks->count(),
                'items' => $testLinks->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->description,
                    'subtitle' => $l->url,
                    'badge' => $l->category,
                    'url' => '/projects/'.$project->id.'/test-data',
                ]),
            ];
        }

        // Project Features (Test Coverage)
        $projectFeatures = $project->features()
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('category', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($projectFeatures->isNotEmpty()) {
            $results[] = [
                'type' => 'project_features',
                'label' => 'Project Features',
                'count' => $projectFeatures->count(),
                'items' => $projectFeatures->map(fn ($f) => [
                    'id' => $f->id,
                    'title' => $f->name,
                    'subtitle' => $f->category,
                    'badge' => $f->priority,
                    'url' => '/projects/'.$project->id.'/test-coverage',
                ]),
            ];
        }

        // Automation Results
        $automationResults = $project->automationTestResults()
            ->where(function ($q) use ($term) {
                $q->where('test_name', 'like', $term)
                    ->orWhere('test_file', 'like', $term)
                    ->orWhere('error_message', 'like', $term);
            })
            ->limit(10)
            ->get();

        if ($automationResults->isNotEmpty()) {
            $results[] = [
                'type' => 'automation_results',
                'label' => 'Automation Results',
                'count' => $automationResults->count(),
                'items' => $automationResults->map(fn ($ar) => [
                    'id' => $ar->id,
                    'title' => $ar->test_name,
                    'subtitle' => $ar->test_file,
                    'badge' => $ar->status,
                    'url' => '/projects/'.$project->id.'/automation',
                ]),
            ];
        }

        $total = collect($results)->sum('count');

        return response()->json([
            'query' => $validated['q'],
            'results' => $results,
            'total' => $total,
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'checklists',
            'testSuites' => fn ($q) => $q->whereNull('parent_id')->with('children'),
            'testRuns' => fn ($q) => $q->latest()->take(5),
            'bugreports' => fn ($q) => $q->latest()->take(5),
            'releases' => fn ($q) => $q->latest()->take(5),
            'documentations' => fn ($q) => $q->whereNull('parent_id')->orderBy('order')->take(5),
        ]);

        return Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            'project' => $project,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->update($validated);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}
