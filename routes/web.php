<?php

use App\Http\Controllers\BugreportController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\TestRunCaseController;
use App\Http\Controllers\TestRunController;
use App\Http\Controllers\TestSuiteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class);

    // Checklists (nested under projects)
    Route::prefix('projects/{project}')->name('checklists.')->group(function () {
        Route::get('checklists', [ChecklistController::class, 'index'])->name('index');
        Route::get('checklists/create', [ChecklistController::class, 'create'])->name('create');
        Route::post('checklists', [ChecklistController::class, 'store'])->name('store');
        Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('show');
        Route::get('checklists/{checklist}/edit', [ChecklistController::class, 'edit'])->name('edit');
        Route::put('checklists/{checklist}', [ChecklistController::class, 'update'])->name('update');
        Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('destroy');
        Route::put('checklists/{checklist}/rows', [ChecklistController::class, 'updateRows'])->name('update-rows');
        Route::put('checklists/{checklist}/note', [ChecklistController::class, 'updateNote'])->name('update-note');
        Route::post('checklists/{checklist}/import-notes', [ChecklistController::class, 'importFromNotes'])->name('import-notes');
    });

    // Test Suites (nested under projects)
    Route::prefix('projects/{project}')->name('test-suites.')->group(function () {
        Route::get('test-suites', [TestSuiteController::class, 'index'])->name('index');
        Route::get('test-suites/create', [TestSuiteController::class, 'create'])->name('create');
        Route::post('test-suites', [TestSuiteController::class, 'store'])->name('store');
        Route::post('test-suites/reorder', [TestSuiteController::class, 'reorder'])->name('reorder');
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
        Route::put('test-cases/{testCase}/note', [TestCaseController::class, 'updateNote'])->name('update-note');
    });

    // Test Runs (nested under projects)
    Route::prefix('projects/{project}')->name('test-runs.')->group(function () {
        Route::get('test-runs', [TestRunController::class, 'index'])->name('index');
        Route::get('test-runs/create', [TestRunController::class, 'create'])->name('create');
        Route::post('test-runs', [TestRunController::class, 'store'])->name('store');
        Route::get('test-runs/{testRun}', [TestRunController::class, 'show'])->name('show');
        Route::get('test-runs/{testRun}/edit', [TestRunController::class, 'edit'])->name('edit');
        Route::put('test-runs/{testRun}', [TestRunController::class, 'update'])->name('update');
        Route::delete('test-runs/{testRun}', [TestRunController::class, 'destroy'])->name('destroy');
        Route::post('test-runs/{testRun}/complete', [TestRunController::class, 'complete'])->name('complete');
        Route::post('test-runs/{testRun}/archive', [TestRunController::class, 'archive'])->name('archive');
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
