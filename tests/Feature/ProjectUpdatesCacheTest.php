<?php

use App\Models\FeatureDescription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('home page populates cache on visit', function () {
    $user = User::factory()->create();
    Cache::store('file')->forget('project_updates_sections');

    $this->actingAs($user)->get(route('project-updates.index'))->assertOk();

    expect(Cache::store('file')->has('project_updates_sections'))->toBeTrue();
});

test('store feature invalidates home cache', function () {
    $user = User::factory()->create();
    Cache::store('file')->put('project_updates_sections', ['stale_cached_value'], 300);

    $response = $this->actingAs($user)
        ->from(route('project-updates.show', 'checklists'))
        ->post(route('project-updates.store-feature', 'checklists'), [
            'title' => 'New custom feature',
        ]);

    $response->assertRedirect();

    expect(Cache::store('file')->has('project_updates_sections'))->toBeFalse();
});

test('update feature invalidates home cache', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 0,
        'title' => 'Original',
        'is_custom' => true,
    ]);
    Cache::store('file')->put('project_updates_sections', ['cached'], 300);

    $this->actingAs($user)->put(route('project-updates.update-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]), [
        'title' => 'Updated',
    ]);

    expect(Cache::store('file')->has('project_updates_sections'))->toBeFalse();
});

test('delete feature invalidates home cache', function () {
    $user = User::factory()->create();
    $feature = FeatureDescription::factory()->create([
        'section_key' => 'checklists',
        'feature_index' => 0,
        'title' => 'To delete',
        'is_custom' => true,
    ]);
    Cache::store('file')->put('project_updates_sections', ['cached'], 300);

    $this->actingAs($user)->delete(route('project-updates.destroy-feature', ['section' => 'checklists', 'featureDescription' => $feature->id]));

    expect(Cache::store('file')->has('project_updates_sections'))->toBeFalse();
});
