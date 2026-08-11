<?php

use App\Models\User;
use App\Services\AchievementService;

test('achievements page requires authentication', function () {
    $this->get('/settings/achievements')->assertRedirect('/login');
});

test('achievements page renders locked state for a fresh user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/achievements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Achievements')
            ->where('achievements.first-blood.unlocked', false)
            ->where('achievements.legend.unlocked', false)
        );
});

test('achievements page reflects unlocked achievements with a date', function () {
    $user = User::factory()->create();
    app(AchievementService::class)->unlock($user, 'first-blood');

    $this->actingAs($user)
        ->get('/settings/achievements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Achievements')
            ->where('achievements.first-blood.unlocked', true)
            ->has('achievements.first-blood.unlocked_at')
        );
});
