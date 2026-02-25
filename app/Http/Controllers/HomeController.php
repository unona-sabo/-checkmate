<?php

namespace App\Http\Controllers;

use App\Http\Requests\Home\UpsertFeatureDescriptionRequest;
use App\Models\AiGeneration;
use App\Models\AutomationTestResult;
use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\DesignLink;
use App\Models\Documentation;
use App\Models\FeatureDescription;
use App\Models\Note;
use App\Models\ProjectFeature;
use App\Models\Release;
use App\Models\TestRun;
use App\Models\TestSuite;
use App\Models\TestUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $configs = $this->getSectionConfigs();

        $sections = Cache::store('file')->remember('home_sections', 300, function () use ($configs) {
            foreach ($configs as $key => $config) {
                $this->syncFeatures($key, $config['features']);
            }

            return array_values(
                array_map(
                    fn (array $config) => $this->buildSection($config['key'], $config['title'], $config['description'], $config['features'], $config['model']),
                    $configs,
                ),
            );
        });

        return Inertia::render('Dashboard', [
            'sections' => $sections,
        ]);
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

    public function storeFeature(UpsertFeatureDescriptionRequest $request, string $section): RedirectResponse
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);

        $validated = $request->validated();

        $maxIndex = FeatureDescription::query()
            ->where('section_key', $section)
            ->max('feature_index') ?? -1;

        FeatureDescription::query()->create([
            'section_key' => $section,
            'feature_index' => $maxIndex + 1,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_custom' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Cache::store('file')->forget('home_sections');

        return back();
    }

    public function updateFeature(UpsertFeatureDescriptionRequest $request, string $section, FeatureDescription $featureDescription): RedirectResponse
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);
        abort_unless($featureDescription->section_key === $section, 404);

        $validated = $request->validated();

        $featureDescription->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        Cache::store('file')->forget('home_sections');

        return back();
    }

    public function destroyFeature(string $section, FeatureDescription $featureDescription): RedirectResponse
    {
        $configs = $this->getSectionConfigs();

        abort_unless(isset($configs[$section]), 404);
        abort_unless($featureDescription->section_key === $section, 404);

        $featureDescription->delete();

        Cache::store('file')->forget('home_sections');

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
                    'Export selected (checked) rows only via File dropdown',
                    'Section headers with custom background/font colors and font weight',
                    'Drag-and-drop row and column reordering',
                    'Dynamic filters by select columns and date range',
                    'Toggle column visibility with localStorage persistence',
                    'Resizable columns with mouse drag',
                    'Multiple note drafts per checklist with auto-save to localStorage',
                    'Import notes by splitting on line breaks into rows',
                    'Clone new checklists from existing templates',
                    'Bulk add multiple rows, add above/below current row',
                    'Undo last save functionality',
                    'Progressive rendering with Load More / Show All',
                    'Full-text search with result highlighting',
                    'Copy link to clipboard',
                    'Drag-and-drop card reordering on index page',
                    'Category grouping with collapsible sections',
                    'Inline category rename from section header',
                    'Import notes into specific section by header',
                    'Copy selected rows to another checklist',
                    'Cross-project row clipboard with smart column mapping',
                    'Bulk actions: create bug report with auto-link back to checklist row',
                    'Bulk actions: create test case with auto-link back to checklist rows',
                    'Bulk actions: create test run from selected rows',
                    'Feature linking per checklist with inline quick-create',
                    'Filter checklists by linked feature on index page',
                    'Deferred rows loading with skeleton placeholder for fast initial render',
                    'Module tagging on checklists and individual rows (UI, API, Backend, Database, Integration)',
                    'Filter rows by module in the filter panel',
                    'AI-powered translation for import notes via TranslateButtons',
                    'Smart row count excluding empty rows and section headers on index page',
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
                    'Module tagging per suite and test case (UI, API, Backend, Database, Integration)',
                    'Tags for categorization',
                    'Creator tracking and filtering by user',
                    'File attachments per test case (images, PDFs, documents, up to 10 MB)',
                    'Notes per test case',
                    'Drag-and-drop reordering within and across suites',
                    'Full-text search with result highlighting',
                    'Color-coded type badges with icons',
                    'Copy link to clipboard',
                    'Feature linking per test case with inline quick-create',
                    'Filter test cases by linked feature on index page',
                    'Feature linking per test suite with inline quick-create',
                    'Cross-project bulk copy with attachments, features, and notes',
                    'Bulk group selected test cases into new subcategory',
                    'CSV export of all or selected test cases with UTF-8 BOM support',
                    'Import test cases from CSV/Excel with auto field mapping and alias support',
                    'AI-powered translation for quick-add steps via TranslateButtons',
                    'Create note with quick-add dialog from test suite page',
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
                    'Create runs from selected checklist rows (each row becomes a check item)',
                    'Comprehensive filter panel: status, source, environment, author, date ranges, stat ranges',
                    'Add cases to active test run without recreating (skips duplicates)',
                    'Source picker dropdown: create new run from test cases or checklist',
                    'Remove individual cases from active test runs with confirmation dialog',
                    'Select All / Deselect All toggle for test cases and checklist rows',
                    'Show expected result for checklist-based checks on expand',
                    'Create bug report directly from any check with pre-filled fields',
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
                    'Filters by status, priority, severity, author, created/updated date ranges',
                    'Create from selected checklist rows with auto-link back',
                    'Copy link to clipboard',
                    'Feature linking per bug report with inline quick-create',
                    'Fixed-on environment tracking (develop, staging, production)',
                ],
                'model' => Bugreport::class,
            ],
            'design' => [
                'key' => 'design',
                'title' => 'Design',
                'description' => 'Centralized hub for design resource links — Figma files, prototypes, brand guidelines, and external tools.',
                'features' => [
                    'Quick links grid with colored icon cards',
                    'Support for Figma, Zeplin, InVision, PDF, and custom URLs',
                    'Category grouping: Figma, Mockups, Assets, Guidelines',
                    'Inline add, edit, and delete via dialogs',
                    'Full-text search by title, description, URL, and category',
                    'Copy link to clipboard',
                    'Open in new tab with external link indicator',
                    'Creator tracking per link',
                ],
                'model' => DesignLink::class,
            ],
            'automation' => [
                'key' => 'automation',
                'title' => 'Automation',
                'description' => 'Playwright integration for automated test execution, discovery, and result tracking with tags and environments.',
                'features' => [
                    'Configure path to existing Playwright test projects',
                    'Auto-discover test files from tests/ and e2e/ directories',
                    'Parse test.describe() and test() blocks with @tag extraction',
                    'Support for .spec.js, .spec.ts, .test.js, .test.ts files',
                    'Link Playwright tests to CheckMate test cases',
                    'Run Playwright tests directly from CheckMate UI',
                    'Import JSON test results with pass/fail/skip/timeout statuses',
                    'Track error messages, stack traces, and execution times',
                    'Latest run statistics dashboard with pass rate',
                    'Results history with environment and tag display',
                    'Test environments with base URL, browser, workers, retries, and custom variables',
                    'Run templates: saved test profiles with environment, tags, and file patterns',
                    'Tag-based filtering with OR/AND mode for targeted test execution',
                    'One-click run from templates with pre-configured settings',
                ],
                'model' => AutomationTestResult::class,
            ],
            'releases' => [
                'key' => 'releases',
                'title' => 'Releases',
                'description' => 'Plan, track, and manage product releases with go/no-go decisions, feature tracking, quality checklists, and metrics.',
                'features' => [
                    'Release lifecycle: planning, development, testing, staging, ready, released',
                    'Go/no-go decision workflow with conditional approvals',
                    'Health indicators: green, yellow, red based on metrics',
                    'Feature tracking with test coverage percentages',
                    'Quality checklist with 5 categories: testing, security, performance, deployment, documentation',
                    'Blocker identification and tracking',
                    'Metrics snapshots with test completion and pass rates',
                    'Release readiness score with weighted breakdown',
                    'Blockers & risks dashboard with security status',
                    'Quality comparison vs previous release',
                    'Link test runs to releases for regression tracking',
                    'Decision tab with auto-recommendation from metrics',
                    'Planned vs actual release date tracking',
                    'Release-level notes and metadata',
                    'Releases section on project dashboard with latest 5 releases',
                ],
                'model' => Release::class,
            ],
            'test-coverage' => [
                'key' => 'test-coverage',
                'title' => 'Test Coverage',
                'description' => 'AI-powered test coverage analytics with gap detection, intelligent recommendations, and coverage trend tracking.',
                'features' => [
                    'Overall coverage percentage with per-module breakdown',
                    'Project feature management with module and category grouping',
                    'Feature-to-test-case linking for coverage tracking',
                    'Auto-matching of test cases to features by title keywords',
                    'Manual test case linking dialog with search and toggle',
                    'AI-powered coverage analysis using Claude API',
                    'Automated coverage gap detection and prioritization',
                    'AI-generated test case suggestions for uncovered features',
                    'Coverage by category: functional, UI, API, security, performance',
                    'Risk assessment with impact analysis and recommendations',
                    'Coverage history tracking with trend visualization',
                    'Search and filter project features',
                    'Priority-based feature organization: critical, high, medium, low',
                    'Checklist coverage tracking alongside test cases',
                    'Separate link dialogs for test cases and checklists',
                    'Multi-module assignment for cross-cutting features',
                    'Module-aware checklist counting in coverage breakdown',
                    'Select All / Deselect All toggle in feature selector',
                ],
                'model' => ProjectFeature::class,
            ],
            'ai-generator' => [
                'key' => 'ai-generator',
                'title' => 'AI Generator',
                'description' => 'AI-powered test case generation from documentation, files, or screenshots with review workflow and direct import to test suites.',
                'features' => [
                    'Generate test cases from pasted text documentation or requirements',
                    'Generate test cases from uploaded TXT and Markdown files',
                    'Generate test cases from uploaded screenshot images',
                    'Multi-provider support: Google Gemini and Anthropic Claude',
                    'Configurable number of test cases per generation (1-20)',
                    'Client-side review and approval flow before import',
                    'Inline editing of generated test cases before import',
                    'Approve all / reject all bulk actions',
                    'Import approved test cases into existing or new test suites',
                    'Steps automatically converted to structured test step format',
                    'Priority and type assignment per generated test case',
                    'Generation usage tracking and analytics',
                    'Persist AI provider selection to localStorage',
                ],
                'model' => AiGeneration::class,
            ],
            'test-data' => [
                'key' => 'test-data',
                'title' => 'Test Data',
                'description' => 'Centralized management of test credentials, user accounts, payment methods, commands, and reference links for QA testing across environments.',
                'features' => [
                    'Test user accounts with name, email, and encrypted passwords',
                    'Role-based test user categorization (admin, user, moderator, tester)',
                    'Environment selection per entry: Develop, Staging, Production',
                    'Validity status tracking for active and expired test accounts',
                    'Additional info field for custom key-value metadata per user',
                    'Tag-based organization for test users and payment methods',
                    'Payment method management: card, crypto, bank, PayPal, and custom types',
                    'Type-specific credential fields with encrypted storage',
                    'Payment system tracking (Stripe, PayPal, Square, Braintree)',
                    'Copy-to-clipboard for emails, passwords, and credentials',
                    'Password visibility toggle with eye icon',
                    'CSV/Excel import and export across all tabs via File dropdown',
                    'Bulk selection and deletion for users, payments, commands, and links',
                    'Search and filter by validity, environment, role, and payment type',
                    'Creator tracking per test data entry',
                    'Drag-and-drop row reordering for all test data types',
                    'Drag-and-drop column reordering with localStorage persistence',
                    'Column resizing with localStorage persistence',
                    'Commands tab for storing deploy, database, testing, and build commands',
                    'Copy-to-clipboard for commands with monospace display',
                    'Category-based grouping and filtering for commands and links',
                    'Links tab for storing reference URLs with descriptions and comments',
                    'Clickable URLs with external link icon for opening in new tabs',
                    'Category autocomplete suggestions from existing entries',
                    'Toggle column visibility per tab with Cols dropdown and localStorage persistence',
                ],
                'model' => TestUser::class,
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
                    'Export documentation as JSON with recursive children',
                    'Import documentation from JSON file as subcategories',
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
                    'AI-powered translation for note content via TranslateButtons',
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
