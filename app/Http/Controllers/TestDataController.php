<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestData\BulkDeleteIdsRequest;
use App\Http\Requests\TestData\UpsertPaymentMethodRequest;
use App\Http\Requests\TestData\UpsertTestUserRequest;
use App\Models\Project;
use App\Models\TestPaymentMethod;
use App\Models\TestUser;
use Illuminate\Http\RedirectResponse;
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

    public function storeUser(UpsertTestUserRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->testUsers()->create([
            ...$validated,
            'created_by' => auth()->id(),
            'order' => ($project->testUsers()->max('order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Test user created successfully.');
    }

    public function updateUser(UpsertTestUserRequest $request, Project $project, TestUser $testUser): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testUser->update($validated);

        return back()->with('success', 'Test user updated successfully.');
    }

    public function destroyUser(Project $project, TestUser $testUser): RedirectResponse
    {
        $this->authorize('update', $project);

        $testUser->delete();

        return back()->with('success', 'Test user deleted successfully.');
    }

    public function bulkDestroyUsers(BulkDeleteIdsRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->testUsers()->whereIn('id', $validated['ids'])->delete();

        return back()->with('success', 'Test users deleted successfully.');
    }

    public function storePayment(UpsertPaymentMethodRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->testPaymentMethods()->create([
            ...$validated,
            'created_by' => auth()->id(),
            'order' => ($project->testPaymentMethods()->max('order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Payment method created successfully.');
    }

    public function updatePayment(UpsertPaymentMethodRequest $request, Project $project, TestPaymentMethod $testPaymentMethod): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testPaymentMethod->update($validated);

        return back()->with('success', 'Payment method updated successfully.');
    }

    public function destroyPayment(Project $project, TestPaymentMethod $testPaymentMethod): RedirectResponse
    {
        $this->authorize('update', $project);

        $testPaymentMethod->delete();

        return back()->with('success', 'Payment method deleted successfully.');
    }

    public function bulkDestroyPayments(BulkDeleteIdsRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->testPaymentMethods()->whereIn('id', $validated['ids'])->delete();

        return back()->with('success', 'Payment methods deleted successfully.');
    }

    public function reorderUsers(BulkDeleteIdsRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        foreach ($validated['ids'] as $index => $id) {
            $project->testUsers()->where('id', $id)->update(['order' => $index]);
        }

        return back();
    }

    public function reorderPayments(BulkDeleteIdsRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        foreach ($validated['ids'] as $index => $id) {
            $project->testPaymentMethods()->where('id', $id)->update(['order' => $index]);
        }

        return back();
    }
}
