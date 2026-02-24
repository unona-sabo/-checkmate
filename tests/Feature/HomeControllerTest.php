<?php

use App\Models\Checklist;
use App\Models\FeatureDescription;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::store('file')->forget('home_sections');
});

test('home page returns sections data for authenticated users', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('sections', 12)
        ->has('sections.0', fn ($section) => $section
            ->where('key', 'checklists')
            ->where('title', 'Checklists')
            ->has('author')
            ->has('count')
            ->has('description')
            ->has('features')
            ->has('latest_created_at')
            ->has('latest_updated_at')
        )
    );
});

test('home page sections include all six modules', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('sections', 12)
        ->where('sections.0.key', 'checklists')
        ->where('sections.1.key', 'test-suites')
        ->where('sections.2.key', 'test-runs')
        ->where('sections.3.key', 'bugreports')
        ->where('sections.4.key', 'design')
        ->where('sections.5.key', 'automation')
        ->where('sections.6.key', 'releases')
        ->where('sections.7.key', 'test-coverage')
        ->where('sections.8.key', 'ai-generator')
        ->where('sections.9.key', 'test-data')
        ->where('sections.10.key', 'documentations')
        ->where('sections.11.key', 'notes')
    );
});

test('show page returns section data and synced features', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home.show', 'checklists'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Show')
        ->has('section', fn ($section) => $section
            ->where('key', 'checklists')
            ->where('title', 'Checklists')
            ->has('author')
            ->has('description')
            ->has('features')
            ->has('count')
            ->has('latest_created_at')
            ->has('latest_updated_at')
        )
        ->has('features', 31)
        ->has('features.0', fn ($feature) => $feature
            ->has('id')
            ->has('title')
            ->has('description')
            ->has('is_custom')
            ->has('created_by')
            ->has('creator')
            ->has('updated_by')
            ->has('updater')
            ->has('section_key')
            ->has('feature_index')
            ->has('created_at')
            ->has('updated_at')
            ->has('deleted_at')
        )
    );
});

test('section author reflects user who last updated a feature description', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'updated_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.key', 'checklists')
        ->where('sections.0.author', 'Jane Doe')
    );
});

test('section author falls back to creator when no updater', function () {
    $user = User::factory()->create(['name' => 'John Smith']);

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'created_by' => $user->id,
        'updated_by' => null,
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.key', 'checklists')
        ->where('sections.0.author', 'John Smith')
    );
});

test('section author defaults to CheckMate Team when no feature descriptions', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.author', 'CheckMate Team')
    );
});

test('section dates come from feature descriptions', function () {
    $user = User::factory()->create();

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 900,
        'updated_by' => $user->id,
        'created_at' => '2020-01-01 00:00:00',
        'updated_at' => '2099-12-31 23:59:59',
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.key', 'checklists')
        ->where('sections.0.latest_created_at', '2020-01-01T00:00:00.000000Z')
        ->where('sections.0.latest_updated_at', '2099-12-31T23:59:59.000000Z')
    );
});

test('update feature sets updated_by to current user', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'title' => 'Original',
        'updated_by' => null,
    ]);

    $this->actingAs($user)->put(route('home.update-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]), [
        'title' => 'Updated',
        'description' => 'Desc',
    ]);

    $this->assertDatabaseHas('feature_descriptions', [
        'id' => $feature->id,
        'updated_by' => $user->id,
    ]);
});

test('section author updates when non-custom feature is edited', function () {
    $user = User::factory()->create(['name' => 'Editor User']);

    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'is_custom' => false,
        'updated_by' => null,
    ]);

    $this->actingAs($user)->put(route('home.update-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]), [
        'title' => 'Edited system feature',
        'description' => 'New desc',
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.key', 'checklists')
        ->where('sections.0.author', 'Editor User')
    );
});

test('home page shows edited feature titles from database', function () {
    $user = User::factory()->create();

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 0,
        'title' => 'Edited feature title',
        'updated_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.key', 'checklists')
        ->where('sections.0.features.0', 'Edited feature title')
    );
});

test('home page excludes deleted features', function () {
    $user = User::factory()->create();

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 900,
        'title' => 'Active custom feature',
        'updated_by' => $user->id,
    ]);

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 901,
        'title' => 'Deleted custom feature',
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();

    $features = $response->original->getData()['page']['props']['sections'][0]['features'];
    expect($features)->toContain('Active custom feature');
    expect($features)->not->toContain('Deleted custom feature');
});

test('home page syncs and displays config features on first load', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.key', 'checklists')
        ->where('sections.0.features.0', 'Custom column types: text, checkbox, select with colors, date')
    );
});

test('home page auto-syncs features for all sections on first load', function () {
    $user = User::factory()->create();

    $this->assertDatabaseCount('feature_descriptions', 0);

    $this->actingAs($user)->get(route('home'));

    expect(FeatureDescription::count())->toBeGreaterThan(0);

    // Verify features exist for all sections
    $sectionKeys = FeatureDescription::query()->distinct()->pluck('section_key')->sort()->values()->all();
    expect($sectionKeys)->toBe(['ai-generator', 'automation', 'bugreports', 'checklists', 'design', 'documentations', 'notes', 'releases', 'test-coverage', 'test-data', 'test-runs', 'test-suites']);
});

test('show page returns 404 for invalid section', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home.show', 'invalid-section'));

    $response->assertNotFound();
});

test('show page syncs config features to database on first visit', function () {
    $user = User::factory()->create();

    $this->assertDatabaseCount('feature_descriptions', 0);

    $this->actingAs($user)->get(route('home.show', 'checklists'));

    $this->assertDatabaseCount('feature_descriptions', 31);
    $this->assertDatabaseHas('feature_descriptions', [
        'section_key' => 'checklists',
        'title' => 'Copy link to clipboard',
        'is_custom' => false,
    ]);
});

test('show page does not duplicate features on repeat visits', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home.show', 'checklists'));
    $this->actingAs($user)->get(route('home.show', 'checklists'));

    $this->assertDatabaseCount('feature_descriptions', 31);
});

test('sync preserves existing features when config changes', function () {
    $user = User::factory()->create();

    FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 99,
        'title' => 'Old feature that was renamed in config',
        'description' => 'User wrote this description',
        'is_custom' => false,
    ]);

    $this->actingAs($user)->get(route('home.show', 'checklists'));

    // Old feature is preserved (31 config + 1 old)
    $this->assertDatabaseCount('feature_descriptions', 32);
    $this->assertDatabaseHas('feature_descriptions', [
        'title' => 'Old feature that was renamed in config',
        'description' => 'User wrote this description',
    ]);
});

test('custom feature can be created', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('home.store-feature', 'checklists'), [
        'title' => 'My custom feature',
        'description' => 'Custom description',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('feature_descriptions', [
        'section_key' => 'checklists',
        'title' => 'My custom feature',
        'description' => 'Custom description',
        'is_custom' => true,
        'created_by' => $user->id,
    ]);
});

test('store returns 404 for invalid section', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('home.store-feature', 'invalid'), [
        'title' => 'Test',
    ]);

    $response->assertNotFound();
});

test('store validates title is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('home.store-feature', 'checklists'), [
        'title' => '',
    ]);

    $response->assertSessionHasErrors('title');
});

test('feature can be updated', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'title' => 'Original title',
        'description' => null,
    ]);

    $response = $this->actingAs($user)->put(route('home.update-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]), [
        'title' => 'Updated title',
        'description' => 'Added description',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('feature_descriptions', [
        'id' => $feature->id,
        'title' => 'Updated title',
        'description' => 'Added description',
    ]);
});

test('update returns 404 for wrong section', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'title' => 'Test',
    ]);

    $response = $this->actingAs($user)->put(route('home.update-feature', ['section' => 'notes', 'featureDescription' => $feature->id]), [
        'title' => 'Updated',
    ]);

    $response->assertNotFound();
});

test('feature can be deleted', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'title' => 'To be deleted',
    ]);

    $response = $this->actingAs($user)->delete(route('home.destroy-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]));

    $response->assertRedirect();

    $this->assertSoftDeleted('feature_descriptions', [
        'id' => $feature->id,
    ]);
});

test('deleted system features are not re-created by sync', function () {
    $user = User::factory()->create();

    // First visit syncs features
    $this->actingAs($user)->get(route('home.show', 'checklists'));
    $this->assertDatabaseCount('feature_descriptions', 31);

    // Delete a system feature
    $feature = FeatureDescription::where('section_key', 'checklists')->first();
    $this->actingAs($user)->delete(route('home.destroy-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]));

    // Second visit should not re-create the deleted feature
    $this->actingAs($user)->get(route('home.show', 'checklists'));

    // Still 31 total (30 active + 1 soft-deleted)
    $this->assertDatabaseCount('feature_descriptions', 31);
    expect(FeatureDescription::where('section_key', 'checklists')->count())->toBe(30);
});

test('delete returns 404 for wrong section', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'title' => 'Test',
    ]);

    $response = $this->actingAs($user)->delete(route('home.destroy-feature', ['section' => 'notes', 'featureDescription' => $feature->id]));

    $response->assertNotFound();
});

test('show, store, update and delete require authentication', function () {
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'title' => 'Test',
    ]);

    $this->get(route('home.show', 'checklists'))->assertRedirect(route('login'));
    $this->post(route('home.store-feature', 'checklists'), ['title' => 'Test'])->assertRedirect(route('login'));
    $this->put(route('home.update-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]), ['title' => 'Test'])->assertRedirect(route('login'));
    $this->delete(route('home.destroy-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]))->assertRedirect(route('login'));
});

test('sync does not overwrite user-edited non-custom features', function () {
    $user = User::factory()->create();

    // First visit syncs features
    $this->actingAs($user)->get(route('home.show', 'checklists'));
    $this->assertDatabaseCount('feature_descriptions', 31);

    // User edits a synced feature
    $feature = FeatureDescription::where('section_key', 'checklists')
        ->where('is_custom', false)
        ->first();
    $originalTitle = $feature->title;

    $this->actingAs($user)->put(route('home.update-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]), [
        'title' => 'User edited title',
        'description' => 'User description',
    ]);

    // Second visit triggers sync again — should NOT revert the edit
    $this->actingAs($user)->get(route('home.show', 'checklists'));

    $this->assertDatabaseHas('feature_descriptions', [
        'id' => $feature->id,
        'title' => 'User edited title',
        'description' => 'User description',
        'updated_by' => $user->id,
    ]);
});

test('all six section keys return valid show pages', function (string $sectionKey) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home.show', $sectionKey));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Show')
        ->where('section.key', $sectionKey)
        ->has('features')
    );
})->with([
    'checklists',
    'test-suites',
    'test-runs',
    'bugreports',
    'test-data',
    'test-coverage',
    'ai-generator',
    'releases',
    'design',
    'automation',
    'documentations',
    'notes',
]);
