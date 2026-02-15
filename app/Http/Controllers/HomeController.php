<?php

namespace App\Http\Controllers;

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\Documentation;
use App\Models\FeatureDescription;
use App\Models\Note;
use App\Models\TestRun;
use App\Models\TestSuite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $sections = array_values(
            array_map(
                fn (array $config) => $this->buildSection($config['key'], $config['title'], $config['description'], $config['features'], $config['model']),
                $this->getSectionConfigs(),
            ),
        );

        return Inertia::render('Dashboard', [
            'sections' => $sections,
        ]);
    }

    public function sync(): RedirectResponse
    {
        foreach ($this->getSectionConfigs() as $key => $config) {
            $this->syncFeatures($key, $config['features']);
        }

        return back();
    }

    public function show(string $section): Response
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);

        $config = $configs[$section];

        $this->syncFeatures($section, $config['features']);

        $sectionData = $this->buildSection($config['key'], $config['title'], $config['description'], $config['features'], $config['model']);

        $features = FeatureDescription::query()
            ->where('section_key', $section)
            ->with('creator:id,name', 'updater:id,name')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Dashboard/Show', [
            'section' => $sectionData,
            'features' => $features,
        ]);
    }

    public function storeFeature(Request $request, string $section): RedirectResponse
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        $maxIndex = FeatureDescription::query()
            ->where('section_key', $section)
            ->max('feature_index') ?? -1;

        FeatureDescription::query()->create([
            'section_key' => $section,
            'feature_index' => $maxIndex + 1,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_custom' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back();
    }

    public function updateFeature(Request $request, string $section, FeatureDescription $featureDescription): RedirectResponse
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);
        abort_unless($featureDescription->section_key === $section, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        $featureDescription->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return back();
    }

    public function destroyFeature(string $section, FeatureDescription $featureDescription): RedirectResponse
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);
        abort_unless($featureDescription->section_key === $section, 404);

        $featureDescription->delete();

        return back();
    }

    /**
     * Sync config features to the database. Only adds new features, never removes existing ones.
     *
     * @param  list<string>  $configFeatures
     */
    private function syncFeatures(string $sectionKey, array $configFeatures): void
    {
        $existing = FeatureDescription::query()
            ->where('section_key', $sectionKey)
            ->where('is_custom', false)
            ->get()
            ->keyBy('feature_index');

        $deletedTitles = FeatureDescription::onlyTrashed()
            ->where('section_key', $sectionKey)
            ->pluck('title')
            ->toArray();

        foreach ($configFeatures as $index => $title) {
            $feature = $existing->get($index);

            if ($feature) {
                if ($feature->title !== $title && $feature->updated_by === null) {
                    $feature->update(['title' => $title]);
                }
            } elseif (! in_array($title, $deletedTitles, true)) {
                FeatureDescription::query()->create([
                    'section_key' => $sectionKey,
                    'feature_index' => $index,
                    'title' => $title,
                    'is_custom' => false,
                ]);
            }
        }
    }

    /**
     * @return array<string, array{key: string, title: string, description: string, features: list<string>, model: class-string<\Illuminate\Database\Eloquent\Model>}>
     */
    private function getSectionConfigs(): array
    {
        return [
            'checklists' => [
                'key' => 'checklists',
                'title' => 'Checklists',
                'description' => 'Flexible tabular checklists with customizable columns for tracking any structured data across projects.',
                'features' => [
                    'Custom column types: text, checkbox, select with colors, date',
                    'CSV import with smart column mapping and export with UTF-8 BOM',
                    'Section headers with custom background/font colors and font weight',
                    'Drag-and-drop row and column reordering',
                    'Dynamic filters by select columns and date range',
                    'Toggle column visibility with localStorage persistence',
                    'Resizable columns with mouse drag',
                    'Notes per checklist with auto-save drafts to localStorage',
                    'Import notes by splitting on line breaks into rows',
                    'Clone new checklists from existing templates',
                    'Bulk add multiple rows, add above/below current row',
                    'Undo last save functionality',
                    'Progressive rendering with Load More / Show All',
                    'Full-text search with result highlighting',
                    'Copy link to clipboard',
                ],
                'model' => Checklist::class,
            ],
            'test-suites' => [
                'key' => 'test-suites',
                'title' => 'Test Suites',
                'description' => 'Hierarchical test case organization and management for structured QA workflows with full traceability.',
                'features' => [
                    'Parent/child suite hierarchy with nested views',
                    '9 test case types: functional, smoke, regression, integration, acceptance, performance, security, usability, other',
                    'Priority levels: low, medium, high, critical',
                    'Severity levels: trivial, minor, major, critical, blocker',
                    'Automation status tracking: not automated, to be automated, automated',
                    'Step-by-step test steps with expected results per step',
                    'Preconditions and expected result fields',
                    'Tags for categorization',
                    'Creator tracking and filtering by user',
                    'File attachments per test case (images, PDFs, documents, up to 10 MB)',
                    'Notes per test case',
                    'Drag-and-drop reordering within and across suites',
                    'Full-text search with result highlighting',
                    'Color-coded type badges with icons',
                    'Copy link to clipboard',
                ],
                'model' => TestSuite::class,
            ],
            'test-runs' => [
                'key' => 'test-runs',
                'title' => 'Test Runs',
                'description' => 'Execute and track test case results with detailed progress monitoring, assignments, and external integrations.',
                'features' => [
                    'Create runs from selected test cases across suites',
                    'Status tracking per case: untested, passed, failed, blocked, skipped, retest',
                    'Visual progress bar with percentage and per-status statistics',
                    'Quick status buttons for rapid result entry',
                    'Bulk update status and assignments for multiple cases',
                    'Assign cases to users, assign-to-me shortcut',
                    'Actual result notes and time spent per case',
                    'External links: ClickUp and Qase integration per case',
                    'Environment and milestone metadata',
                    'Run lifecycle: active, completed, archived',
                    'Track started, completed timestamps and completed-by user',
                    'Pause/resume timer to exclude breaks from elapsed time',
                    'Live elapsed time display with automatic ticking for active runs',
                    'Creator tracking per test run',
                    'Group test cases by suite with section headers',
                    'Filter by status and assigned user',
                    'Full-text search with result highlighting',
                    'Copy link to clipboard',
                ],
                'model' => TestRun::class,
            ],
            'bugreports' => [
                'key' => 'bugreports',
                'title' => 'Bug Reports',
                'description' => 'Comprehensive issue tracking and bug management with full lifecycle, triage levels, and file attachments.',
                'features' => [
                    'Status workflow: new, open, in progress, resolved, closed, reopened',
                    'Severity levels: critical, major, minor, trivial',
                    'Priority levels: high, medium, low',
                    'Steps to reproduce with expected and actual results',
                    'Environment field for test environment details',
                    'Reporter and assignee tracking per bug',
                    'File attachments with image preview gallery (up to 10 MB)',
                    'Download links with file size display',
                    'Color-coded severity and priority badges',
                    'Full-text search by title and description with highlighting',
                    'Copy link to clipboard',
                ],
                'model' => Bugreport::class,
            ],
            'documentations' => [
                'key' => 'documentations',
                'title' => 'Documentation',
                'description' => 'Nested knowledge base with rich content editing, hierarchical organization, and full attachment support.',
                'features' => [
                    'Multi-level hierarchy: parent, child, grandchild pages',
                    'Rich text editor with inline image uploads',
                    'Category tagging per page',
                    'Tree navigation sidebar with search',
                    'Drag-and-drop page reordering',
                    'File attachments: images with gallery, documents with download',
                    'Subcategories section with quick-add button',
                    'Breadcrumb navigation through hierarchy',
                    'Recursive search through all hierarchy levels',
                    'Content search highlighting within pages',
                    'Document tree sidebar on index page with search and filtering',
                    'Copy link to clipboard',
                ],
                'model' => Documentation::class,
            ],
            'notes' => [
                'key' => 'notes',
                'title' => 'Notes',
                'description' => 'Quick draft notes with publish capability for rapid idea capture and seamless integration with other modules.',
                'features' => [
                    'Draft note creation with title and content',
                    'Publish drafts directly to documentation pages',
                    'Import notes into checklist rows by splitting on line breaks',
                    'Select target checklist and column for import',
                    'Link notes to documentation pages',
                    'Draft/published status badges',
                    'Auto-save drafts to localStorage with timestamps',
                    'Restore draft capability with preview',
                    'Per-project note organization',
                    'Card-based grid layout with content preview',
                    'Delete confirmation dialog',
                    'Copy link to clipboard',
                ],
                'model' => Note::class,
            ],
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  list<string>  $features
     * @return array{key: string, title: string, description: string, features: list<string>, author: string, count: int, latest_created_at: string|null, latest_updated_at: string|null}
     */
    private function buildSection(string $key, string $title, string $description, array $configFeatures, string $modelClass): array
    {
        $featureQuery = FeatureDescription::query()->where('section_key', $key);

        $dbFeatures = (clone $featureQuery)->orderBy('feature_index')->orderBy('created_at')->pluck('title')->all();

        $features = $dbFeatures !== [] ? $dbFeatures : $configFeatures;

        $latestFeature = (clone $featureQuery)->latest('updated_at')->with('updater:id,name', 'creator:id,name')->first();
        $oldestFeature = (clone $featureQuery)->oldest('created_at')->first();

        $author = $latestFeature?->updater?->name
            ?? $latestFeature?->creator?->name
            ?? 'CheckMate Team';

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'features' => $features,
            'author' => $author,
            'count' => count($features),
            'latest_created_at' => $oldestFeature?->created_at,
            'latest_updated_at' => $latestFeature?->updated_at,
        ];
    }
}
