<?php

use App\Models\AiSetting;
use App\Models\Workspace;
use App\Services\AITestGeneratorService;
use App\Services\CoverageAnalysisService;
use Illuminate\Support\Facades\Http;

test('ai settings page is displayed', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->get(route('workspaces.ai.show', $workspace));

    $response->assertOk();
});

test('ai settings page requires authentication', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->get(route('workspaces.ai.show', $workspace));

    $response->assertRedirect(route('login'));
});

test('member cannot view ai settings page', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $response = $this->actingAs($user)->get(route('workspaces.ai.show', $workspace));

    $response->assertRedirect(route('projects.index'));
    $response->assertSessionHas('error');
});

test('viewer cannot view ai settings page', function () {
    [$user, $workspace] = createUserWithWorkspace('viewer');

    $response = $this->actingAs($user)->get(route('workspaces.ai.show', $workspace));

    $response->assertRedirect(route('projects.index'));
    $response->assertSessionHas('error');
});

test('ai settings can be saved', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.ai.update', $workspace), [
        'gemini_api_key' => 'gemini-test-key',
        'gemini_model' => 'gemini-2.0-flash',
        'anthropic_api_key' => 'anthropic-test-key',
        'openai_api_key' => 'openai-test-key',
        'openai_model' => 'gpt-4o-mini',
        'default_provider' => 'gemini',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $settings = AiSetting::forWorkspace($workspace);
    expect($settings->apiKeyFor('gemini'))->toBe('gemini-test-key');
    expect($settings->apiKeyFor('claude'))->toBe('anthropic-test-key');
    expect($settings->apiKeyFor('openai'))->toBe('openai-test-key');
    expect($settings->gemini_model)->toBe('gemini-2.0-flash');
    expect($settings->openai_model)->toBe('gpt-4o-mini');
    expect($settings->default_provider)->toBe('gemini');
    expect($settings->isConfigured())->toBeTrue();
});

test('blank api key inputs keep the existing saved key', function () {
    [$user, $workspace] = createUserWithWorkspace();
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'existing-key']);

    $response = $this->actingAs($user)->put(route('workspaces.ai.update', $workspace), [
        'gemini_api_key' => '',
        'default_provider' => 'gemini',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(AiSetting::forWorkspace($workspace)->apiKeyFor('gemini'))->toBe('existing-key');
});

test('ai settings validation requires a valid default provider', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.ai.update', $workspace), [
        'default_provider' => 'invalid',
    ]);

    $response->assertSessionHasErrors(['default_provider']);
});

test('member cannot update ai settings', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $response = $this->actingAs($user)->put(route('workspaces.ai.update', $workspace), [
        'gemini_api_key' => 'gemini-test-key',
        'default_provider' => 'gemini',
    ]);

    $response->assertRedirect(route('projects.index'));
    $response->assertSessionHas('error');
});

test('viewer cannot update ai settings', function () {
    [$user, $workspace] = createUserWithWorkspace('viewer');

    $response = $this->actingAs($user)->put(route('workspaces.ai.update', $workspace), [
        'gemini_api_key' => 'gemini-test-key',
        'default_provider' => 'gemini',
    ]);

    $response->assertRedirect(route('projects.index'));
    $response->assertSessionHas('error');
});

test('two workspaces have independent ai settings', function () {
    [$userA, $workspaceA] = createUserWithWorkspace();
    [$userB, $workspaceB] = createUserWithWorkspace();

    $this->actingAs($userA)->put(route('workspaces.ai.update', $workspaceA), [
        'gemini_api_key' => 'workspace-a-key',
        'default_provider' => 'gemini',
    ]);

    $this->actingAs($userB)->put(route('workspaces.ai.update', $workspaceB), [
        'gemini_api_key' => 'workspace-b-key',
        'default_provider' => 'gemini',
    ]);

    expect(AiSetting::forWorkspace($workspaceA)->apiKeyFor('gemini'))->toBe('workspace-a-key');
    expect(AiSetting::forWorkspace($workspaceB)->apiKeyFor('gemini'))->toBe('workspace-b-key');
});

test('a workspace gemini key does not leak into another workspace generation request', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400),
    ]);

    [$userA, $workspaceA] = createUserWithWorkspace();
    AiSetting::forWorkspace($workspaceA)->update(['gemini_api_key' => 'workspace-a-key']);

    [$userB, $workspaceB] = createUserWithWorkspace();

    $settingsB = AiSetting::forWorkspace($workspaceB);
    expect($settingsB->apiKeyFor('gemini'))->toBeNull();

    $service = new AITestGeneratorService('gemini', $settingsB->apiKeyFor('gemini'));

    expect(fn () => $service->generateFromText('Login feature description'))
        ->toThrow(Exception::class, 'Gemini API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
});

test('a workspace claude key does not leak into another workspace claude service', function () {
    [$userA, $workspaceA] = createUserWithWorkspace();
    AiSetting::forWorkspace($workspaceA)->update(['anthropic_api_key' => 'workspace-a-key']);

    [, $workspaceB] = createUserWithWorkspace();
    $settingsB = AiSetting::forWorkspace($workspaceB);
    expect($settingsB->apiKeyFor('claude'))->toBeNull();

    $service = new CoverageAnalysisService('claude', $settingsB->apiKeyFor('claude'));

    expect(fn () => $service->analyzeCoverage([], []))
        ->toThrow(Exception::class, 'Anthropic API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
});
