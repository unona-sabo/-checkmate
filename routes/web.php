<?php

use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BugreportController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DesignLinkController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\TestCoverageController;
use App\Http\Controllers\TestDataController;
use App\Http\Controllers\TestEnvironmentController;
use App\Http\Controllers\TestRunCaseController;
use App\Http\Controllers\TestRunController;
use App\Http\Controllers\TestRunTemplateController;
use App\Http\Controllers\TestSuiteController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('home', [HomeController::class, 'index'])->name('home');
    Route::post('home/sync', [HomeController::class, 'sync'])->name('home.sync');
    Route::get('home/{section}', [HomeController::class, 'show'])->name('home.show');
    Route::post('home/{section}/features', [HomeController::class, 'storeFeature'])->name('home.store-feature');
    Route::put('home/{section}/features/{featureDescription}', [HomeController::class, 'updateFeature'])->name('home.update-feature');
    Route::delete('home/{section}/features/{featureDescription}', [HomeController::class, 'destroyFeature'])->name('home.destroy-feature');

    // Workspaces
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/switch', [WorkspaceController::class, 'switchWorkspace'])->name('workspaces.switch');
    Route::get('workspaces/settings', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::put('workspaces/settings', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('workspaces/settings', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('workspaces/members', [WorkspaceMemberController::class, 'store'])->name('workspaces.members.store');
    Route::put('workspaces/members/{member}', [WorkspaceMemberController::class, 'update'])->name('workspaces.members.update');
    Route::delete('workspaces/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');

    // Projects
    Route::post('projects/reorder', [ProjectController::class, 'reorder'])->name('projects.reorder');
    Route::get('projects/{project}/search', [ProjectController::class, 'search'])->name('projects.search');
    Route::resource('projects', ProjectController::class);

    // Checklists (nested under projects)
    Route::prefix('projects/{project}')->name('checklists.')->group(function () {
        Route::get('checklists', [ChecklistController::class, 'index'])->name('index');
        Route::get('checklists/create', [ChecklistController::class, 'create'])->name('create');
        Route::post('checklists', [ChecklistController::class, 'store'])->name('store');
        Route::put('checklists/reorder', [ChecklistController::class, 'reorder'])->name('reorder');
        Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('show');
        Route::get('checklists/{checklist}/edit', [ChecklistController::class, 'edit'])->name('edit');
        Route::put('checklists/{checklist}', [ChecklistController::class, 'update'])->name('update');
        Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('destroy');
        Route::put('checklists/{checklist}/rows', [ChecklistController::class, 'updateRows'])->name('update-rows');
        Route::put('checklists/{checklist}/note', [ChecklistController::class, 'updateNote'])->name('update-note');
        Route::post('checklists/{checklist}/import-notes', [ChecklistController::class, 'importFromNotes'])->name('import-notes');
        Route::get('checklists/{checklist}/export', [ChecklistController::class, 'export'])->name('export');
        Route::post('checklists/{checklist}/import', [ChecklistController::class, 'import'])->name('import');
        Route::post('checklists/{checklist}/copy-rows', [ChecklistController::class, 'copyRows'])->name('copy-rows');
    });

    // Test Suites (nested under projects)
    Route::prefix('projects/{project}')->name('test-suites.')->group(function () {
        Route::get('test-suites', [TestSuiteController::class, 'index'])->name('index');
        Route::get('test-suites/create', [TestSuiteController::class, 'create'])->name('create');
        Route::post('test-suites', [TestSuiteController::class, 'store'])->name('store');
        Route::post('test-suites/reorder', [TestSuiteController::class, 'reorder'])->name('reorder');
        Route::post('test-suites/reorder-cases', [TestCaseController::class, 'reorderAcrossSuites'])->name('reorder-cases');
        Route::get('test-suites/{testSuite}', [TestSuiteController::class, 'show'])->name('show');
        Route::get('test-suites/{testSuite}/edit', [TestSuiteController::class, 'edit'])->name('edit');
        Route::put('test-suites/{testSuite}', [TestSuiteController::class, 'update'])->name('update');
        Route::delete('test-suites/{testSuite}', [TestSuiteController::class, 'destroy'])->name('destroy');
    });

    // Test Cases (nested under test suites)
    Route::prefix('projects/{project}/test-suites/{testSuite}')->name('test-cases.')->group(function () {
        Route::get('test-cases/create', [TestCaseController::class, 'create'])->name('create');
        Route::post('test-cases', [TestCaseController::class, 'store'])->name('store');
        Route::post('test-cases/reorder', [TestCaseController::class, 'reorder'])->name('reorder');
        Route::get('test-cases/{testCase}', [TestCaseController::class, 'show'])->name('show');
        Route::get('test-cases/{testCase}/edit', [TestCaseController::class, 'edit'])->name('edit');
        Route::put('test-cases/{testCase}', [TestCaseController::class, 'update'])->name('update');
        Route::delete('test-cases/{testCase}', [TestCaseController::class, 'destroy'])->name('destroy');
        Route::delete('test-cases/{testCase}/attachments/{attachment}', [TestCaseController::class, 'destroyAttachment'])->name('destroy-attachment');
        Route::put('test-cases/{testCase}/note', [TestCaseController::class, 'updateNote'])->name('update-note');
    });

    // Test Runs (nested under projects)
    Route::prefix('projects/{project}')->name('test-runs.')->group(function () {
        Route::get('test-runs', [TestRunController::class, 'index'])->name('index');
        Route::get('test-runs/create', [TestRunController::class, 'create'])->name('create');
        Route::post('test-runs', [TestRunController::class, 'store'])->name('store');
        Route::post('test-runs/from-checklist', [TestRunController::class, 'storeFromChecklist'])->name('store-from-checklist');
        Route::get('test-runs/{testRun}', [TestRunController::class, 'show'])->name('show');
        Route::get('test-runs/{testRun}/edit', [TestRunController::class, 'edit'])->name('edit');
        Route::put('test-runs/{testRun}', [TestRunController::class, 'update'])->name('update');
        Route::delete('test-runs/{testRun}', [TestRunController::class, 'destroy'])->name('destroy');
        Route::post('test-runs/{testRun}/complete', [TestRunController::class, 'complete'])->name('complete');
        Route::post('test-runs/{testRun}/archive', [TestRunController::class, 'archive'])->name('archive');
        Route::post('test-runs/{testRun}/pause', [TestRunController::class, 'pause'])->name('pause');
        Route::post('test-runs/{testRun}/resume', [TestRunController::class, 'resume'])->name('resume');
    });

    // Test Run Cases
    Route::prefix('projects/{project}/test-runs/{testRun}')->name('test-run-cases.')->group(function () {
        Route::put('cases/{testRunCase}', [TestRunCaseController::class, 'update'])->name('update');
        Route::post('cases/bulk-update', [TestRunCaseController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('cases/{testRunCase}/assign-to-me', [TestRunCaseController::class, 'assignToMe'])->name('assign-to-me');
    });

    // Bugreports (nested under projects)
    Route::prefix('projects/{project}')->name('bugreports.')->group(function () {
        Route::get('bugreports', [BugreportController::class, 'index'])->name('index');
        Route::get('bugreports/create', [BugreportController::class, 'create'])->name('create');
        Route::post('bugreports', [BugreportController::class, 'store'])->name('store');
        Route::get('bugreports/{bugreport}', [BugreportController::class, 'show'])->name('show');
        Route::get('bugreports/{bugreport}/edit', [BugreportController::class, 'edit'])->name('edit');
        Route::put('bugreports/{bugreport}', [BugreportController::class, 'update'])->name('update');
        Route::delete('bugreports/{bugreport}', [BugreportController::class, 'destroy'])->name('destroy');
        Route::delete('bugreports/{bugreport}/attachments/{attachment}', [BugreportController::class, 'destroyAttachment'])->name('destroy-attachment');
    });

    // Design Links (nested under projects)
    Route::prefix('projects/{project}')->name('design-links.')->group(function () {
        Route::get('design', [DesignLinkController::class, 'index'])->name('index');
        Route::post('design', [DesignLinkController::class, 'store'])->name('store');
        Route::put('design/{designLink}', [DesignLinkController::class, 'update'])->name('update');
        Route::delete('design/{designLink}', [DesignLinkController::class, 'destroy'])->name('destroy');
    });

    // Automation / Playwright Integration (nested under projects)
    Route::prefix('projects/{project}')->name('automation.')->group(function () {
        Route::get('automation', [AutomationController::class, 'index'])->name('index');
        Route::put('automation/config', [AutomationController::class, 'updateConfig'])->name('update-config');
        Route::get('automation/scan', [AutomationController::class, 'scan'])->name('scan');
        Route::post('automation/run', [AutomationController::class, 'run'])->name('run');
        Route::post('automation/import-results', [AutomationController::class, 'importResults'])->name('import-results');
        Route::post('automation/link-test-case', [AutomationController::class, 'linkTestCase'])->name('link-test-case');
        Route::post('automation/unlink-test-case', [AutomationController::class, 'unlinkTestCase'])->name('unlink-test-case');
        Route::delete('automation/clear-results', [AutomationController::class, 'clearResults'])->name('clear-results');

        // Environments
        Route::post('automation/environments', [TestEnvironmentController::class, 'store'])->name('environments.store');
        Route::put('automation/environments/{environment}', [TestEnvironmentController::class, 'update'])->name('environments.update');
        Route::delete('automation/environments/{environment}', [TestEnvironmentController::class, 'destroy'])->name('environments.destroy');

        // Templates
        Route::post('automation/templates', [TestRunTemplateController::class, 'store'])->name('templates.store');
        Route::put('automation/templates/{template}', [TestRunTemplateController::class, 'update'])->name('templates.update');
        Route::delete('automation/templates/{template}', [TestRunTemplateController::class, 'destroy'])->name('templates.destroy');
    });

    // Releases (nested under projects)
    Route::prefix('projects/{project}')->name('releases.')->group(function () {
        Route::get('releases', [ReleaseController::class, 'index'])->name('index');
        Route::post('releases', [ReleaseController::class, 'store'])->name('store');
        Route::get('releases/{release}', [ReleaseController::class, 'show'])->name('show');
        Route::put('releases/{release}', [ReleaseController::class, 'update'])->name('update');
        Route::delete('releases/{release}', [ReleaseController::class, 'destroy'])->name('destroy');
        Route::post('releases/{release}/refresh-metrics', [ReleaseController::class, 'refreshMetrics'])->name('refresh-metrics');
        Route::post('releases/{release}/features', [ReleaseController::class, 'storeFeature'])->name('features.store');
        Route::put('releases/{release}/features/{releaseFeature}', [ReleaseController::class, 'updateFeature'])->name('features.update');
        Route::delete('releases/{release}/features/{releaseFeature}', [ReleaseController::class, 'destroyFeature'])->name('features.destroy');
        Route::post('releases/{release}/checklist-items', [ReleaseController::class, 'storeChecklistItem'])->name('checklist-items.store');
        Route::put('releases/{release}/checklist-items/{item}', [ReleaseController::class, 'updateChecklistItem'])->name('checklist-items.update');
        Route::delete('releases/{release}/checklist-items/{item}', [ReleaseController::class, 'destroyChecklistItem'])->name('checklist-items.destroy');
        Route::post('releases/{release}/test-runs', [ReleaseController::class, 'linkTestRun'])->name('test-runs.link');
        Route::delete('releases/{release}/test-runs/{testRun}', [ReleaseController::class, 'unlinkTestRun'])->name('test-runs.unlink');
    });

    // Test Coverage (nested under projects)
    Route::prefix('projects/{project}')->name('test-coverage.')->group(function () {
        Route::get('test-coverage', [TestCoverageController::class, 'index'])->name('index');
        Route::post('test-coverage/ai-analysis', [TestCoverageController::class, 'runAIAnalysis'])->name('ai-analysis');
        Route::post('test-coverage/generate-test-cases', [TestCoverageController::class, 'generateTestCases'])->name('generate-test-cases');
        Route::get('test-coverage/history', [TestCoverageController::class, 'coverageHistory'])->name('history');
        Route::post('test-coverage/features', [TestCoverageController::class, 'storeFeature'])->name('features.store');
        Route::put('test-coverage/features/{feature}', [TestCoverageController::class, 'updateFeature'])->name('features.update');
        Route::delete('test-coverage/features/{feature}', [TestCoverageController::class, 'destroyFeature'])->name('features.destroy');
        Route::post('test-coverage/features/{feature}/link-test-case', [TestCoverageController::class, 'linkTestCase'])->name('features.link-test-case');
        Route::delete('test-coverage/features/{feature}/test-cases/{testCase}', [TestCoverageController::class, 'unlinkTestCase'])->name('features.unlink-test-case');
        Route::post('test-coverage/auto-link-all', [TestCoverageController::class, 'autoLinkAll'])->name('auto-link-all');
        Route::post('test-coverage/features/{feature}/auto-link', [TestCoverageController::class, 'autoLinkSingle'])->name('features.auto-link');
        Route::get('test-coverage/test-cases', [TestCoverageController::class, 'getTestCases'])->name('test-cases');
    });

    // Test Data (nested under projects)
    Route::prefix('projects/{project}')->name('test-data.')->group(function () {
        Route::get('test-data', [TestDataController::class, 'index'])->name('index');
        Route::post('test-data/users', [TestDataController::class, 'storeUser'])->name('users.store');
        Route::put('test-data/users/{testUser}', [TestDataController::class, 'updateUser'])->name('users.update');
        Route::delete('test-data/users/{testUser}', [TestDataController::class, 'destroyUser'])->name('users.destroy');
        Route::delete('test-data/users-bulk', [TestDataController::class, 'bulkDestroyUsers'])->name('users.bulk-destroy');
        Route::post('test-data/payments', [TestDataController::class, 'storePayment'])->name('payments.store');
        Route::put('test-data/payments/{testPaymentMethod}', [TestDataController::class, 'updatePayment'])->name('payments.update');
        Route::delete('test-data/payments/{testPaymentMethod}', [TestDataController::class, 'destroyPayment'])->name('payments.destroy');
        Route::delete('test-data/payments-bulk', [TestDataController::class, 'bulkDestroyPayments'])->name('payments.bulk-destroy');
        Route::put('test-data/users-reorder', [TestDataController::class, 'reorderUsers'])->name('users.reorder');
        Route::put('test-data/payments-reorder', [TestDataController::class, 'reorderPayments'])->name('payments.reorder');
    });

    // Documentations (nested under projects)
    Route::prefix('projects/{project}')->name('documentations.')->group(function () {
        Route::get('documentations', [DocumentationController::class, 'index'])->name('index');
        Route::get('documentations/create', [DocumentationController::class, 'create'])->name('create');
        Route::post('documentations', [DocumentationController::class, 'store'])->name('store');
        Route::post('documentations/reorder', [DocumentationController::class, 'reorder'])->name('reorder');
        Route::get('documentations/{documentation}', [DocumentationController::class, 'show'])->name('show');
        Route::get('documentations/{documentation}/edit', [DocumentationController::class, 'edit'])->name('edit');
        Route::put('documentations/{documentation}', [DocumentationController::class, 'update'])->name('update');
        Route::delete('documentations/{documentation}', [DocumentationController::class, 'destroy'])->name('destroy');
        Route::delete('documentations/{documentation}/attachments/{attachment}', [DocumentationController::class, 'destroyAttachment'])->name('destroy-attachment');
        Route::post('documentations/{documentation}/upload-image', [DocumentationController::class, 'uploadImage'])->name('upload-image');
        Route::post('documentations/upload-image', [DocumentationController::class, 'uploadNewImage'])->name('upload-new-image');
    });

    // Notes (nested under projects)
    Route::prefix('projects/{project}')->name('projects.notes.')->group(function () {
        Route::get('notes', [NoteController::class, 'index'])->name('index');
        Route::get('notes/create', [NoteController::class, 'create'])->name('create');
        Route::post('notes', [NoteController::class, 'store'])->name('store');
        Route::get('notes/{note}', [NoteController::class, 'show'])->name('show');
        Route::put('notes/{note}', [NoteController::class, 'update'])->name('update');
        Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('destroy');
        Route::post('notes/{note}/publish', [NoteController::class, 'publish'])->name('publish');
    });
});

require __DIR__.'/settings.php';
