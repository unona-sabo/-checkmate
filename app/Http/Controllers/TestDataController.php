<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestPaymentMethod;
use App\Models\TestUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestDataController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $testUsers = $project->testUsers()
            ->with('creator:id,name')
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        $testPaymentMethods = $project->testPaymentMethods()
            ->with('creator:id,name')
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('TestData/Index', [
            'project' => $project,
            'testUsers' => $testUsers,
            'testPaymentMethods' => $testPaymentMethods,
        ]);
    }

    public function storeUser(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'nullable|string|max:1000',
            'role' => 'nullable|string|max:100',
            'environment' => 'nullable|string|in:develop,staging,production',
            'description' => 'nullable|string|max:2000',
            'is_valid' => 'boolean',
            'additional_info' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);

        $project->testUsers()->create([
            ...$validated,
            'created_by' => auth()->id(),
            'order' => ($project->testUsers()->max('order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Test user created successfully.');
    }

    public function updateUser(Request $request, Project $project, TestUser $testUser): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'nullable|string|max:1000',
            'role' => 'nullable|string|max:100',
            'environment' => 'nullable|string|in:develop,staging,production',
            'description' => 'nullable|string|max:2000',
            'is_valid' => 'boolean',
            'additional_info' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);

        $testUser->update($validated);

        return back()->with('success', 'Test user updated successfully.');
    }

    public function destroyUser(Project $project, TestUser $testUser): RedirectResponse
    {
        $this->authorize('update', $project);

        $testUser->delete();

        return back()->with('success', 'Test user deleted successfully.');
    }

    public function bulkDestroyUsers(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $project->testUsers()->whereIn('id', $validated['ids'])->delete();

        return back()->with('success', 'Test users deleted successfully.');
    }

    public function storePayment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:card,crypto,bank,paypal,other',
            'system' => 'nullable|string|max:255',
            'credentials' => 'nullable|array',
            'environment' => 'nullable|string|in:develop,staging,production',
            'is_valid' => 'boolean',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);

        $project->testPaymentMethods()->create([
            ...$validated,
            'created_by' => auth()->id(),
            'order' => ($project->testPaymentMethods()->max('order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Payment method created successfully.');
    }

    public function updatePayment(Request $request, Project $project, TestPaymentMethod $testPaymentMethod): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:card,crypto,bank,paypal,other',
            'system' => 'nullable|string|max:255',
            'credentials' => 'nullable|array',
            'environment' => 'nullable|string|in:develop,staging,production',
            'is_valid' => 'boolean',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);

        $testPaymentMethod->update($validated);

        return back()->with('success', 'Payment method updated successfully.');
    }

    public function destroyPayment(Project $project, TestPaymentMethod $testPaymentMethod): RedirectResponse
    {
        $this->authorize('update', $project);

        $testPaymentMethod->delete();

        return back()->with('success', 'Payment method deleted successfully.');
    }

    public function bulkDestroyPayments(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $project->testPaymentMethods()->whereIn('id', $validated['ids'])->delete();

        return back()->with('success', 'Payment methods deleted successfully.');
    }

    public function reorderUsers(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            $project->testUsers()->where('id', $id)->update(['order' => $index]);
        }

        return back();
    }

    public function reorderPayments(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            $project->testPaymentMethods()->where('id', $id)->update(['order' => $index]);
        }

        return back();
    }
}
