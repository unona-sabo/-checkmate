<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Home\UpsertFeatureDescriptionRequest;
use App\Models\FeatureDescription;
use App\Services\SectionConfigRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ProjectUpdatesController extends Controller
{
    public function __construct(private readonly SectionConfigRegistry $sectionConfigRegistry) {}

    public function index(): Response
    {
        $configs = $this->sectionConfigRegistry->all();

        $sections = Cache::store('file')->remember('project_updates_sections', 300, function () use ($configs) {
            foreach ($configs as $key => $config) {
                $this->syncFeatures($key, $config['features']);
            }

            return array_values(
                array_map(
                    fn (array $config) => $this->buildSection($config['key'], $config['title'], $config['description'], $config['features'], $config['model'] ?? null),
                    $configs,
                ),
            );
        });

        return Inertia::render('settings/ProjectUpdates', [
            'sections' => $sections,
        ]);
    }

    public function show(string $section): Response
    {
        $configs = $this->sectionConfigRegistry->all();

        abort_unless(isset($configs[$section]), 404);

        $config = $configs[$section];

        $this->syncFeatures($section, $config['features']);

        $sectionData = $this->buildSection($config['key'], $config['title'], $config['description'], $config['features'], $config['model'] ?? null);

        $features = FeatureDescription::query()
            ->where('section_key', $section)
            ->with('creator:id,name', 'updater:id,name')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('settings/ProjectUpdates/Show', [
            'section' => $sectionData,
            'features' => $features,
        ]);
    }

    public function storeFeature(UpsertFeatureDescriptionRequest $request, string $section): RedirectResponse
    {
        $configs = $this->sectionConfigRegistry->all();

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

        Cache::store('file')->forget('project_updates_sections');

        return back();
    }

    public function updateFeature(UpsertFeatureDescriptionRequest $request, string $section, FeatureDescription $featureDescription): RedirectResponse
    {
        $configs = $this->sectionConfigRegistry->all();

        abort_unless(isset($configs[$section]), 404);
        abort_unless($featureDescription->section_key === $section, 404);

        $validated = $request->validated();

        $featureDescription->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        Cache::store('file')->forget('project_updates_sections');

        return back();
    }

    public function destroyFeature(string $section, FeatureDescription $featureDescription): RedirectResponse
    {
        $configs = $this->sectionConfigRegistry->all();

        abort_unless(isset($configs[$section]), 404);
        abort_unless($featureDescription->section_key === $section, 404);

        $featureDescription->delete();

        Cache::store('file')->forget('project_updates_sections');

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

        $now = now();
        $toCreate = [];

        foreach ($configFeatures as $index => $title) {
            $feature = $existing->get($index);

            if ($feature) {
                if ($feature->title !== $title && $feature->updated_by === null) {
                    $feature->update(['title' => $title]);
                }
            } elseif (! in_array($title, $deletedTitles, true)) {
                $toCreate[] = [
                    'section_key' => $sectionKey,
                    'feature_index' => $index,
                    'title' => $title,
                    'is_custom' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($toCreate !== []) {
            FeatureDescription::query()->insert($toCreate);
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>|null  $modelClass
     * @param  list<string>  $features
     * @return array{key: string, title: string, description: string, features: list<string>, author: string, count: int, latest_created_at: string|null, latest_updated_at: string|null}
     */
    private function buildSection(string $key, string $title, string $description, array $configFeatures, ?string $modelClass = null): array
    {
        $allFeatures = FeatureDescription::query()
            ->where('section_key', $key)
            ->with('creator:id,name', 'updater:id,name')
            ->get();

        $dbFeatures = $allFeatures
            ->sortBy([['feature_index', 'asc'], ['created_at', 'asc']])
            ->pluck('title')
            ->all();

        $features = $dbFeatures !== [] ? $dbFeatures : $configFeatures;

        // `id` breaks ties between rows with the same second-precision
        // timestamp (e.g. a batch of features synced in the same request).
        $latestFeature = $allFeatures->sortBy([['updated_at', 'desc'], ['id', 'desc']])->first();
        $oldestFeature = $allFeatures->sortBy([['created_at', 'asc'], ['id', 'asc']])->first();

        // The author is deliberately picked from human-edited rows only, so
        // an anonymous feature synced from config in this same request never
        // outranks someone's actual edit just because it landed a moment
        // later.
        $lastUpdatedByUser = $allFeatures->whereNotNull('updated_by')
            ->sortBy([['updated_at', 'desc'], ['id', 'desc']])
            ->first();
        $lastCreatedByUser = $allFeatures->whereNotNull('created_by')
            ->sortBy([['created_at', 'desc'], ['id', 'desc']])
            ->first();

        $author = $lastUpdatedByUser?->updater?->name
            ?? $lastCreatedByUser?->creator?->name
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
