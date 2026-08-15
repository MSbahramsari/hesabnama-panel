<?php

use App\Models\User;

it('does not expose demo credentials on the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertDontSee('حساب آزمایشی')
        ->assertDontSee('demo@moadian.test')
        ->assertDontSee('name="remember"', false);

    expect(config('session.lifetime'))->toBe(180);
});

it('purges only the known seeded demo accounts', function () {
    $realUser = User::factory()->create(['email' => 'real@example.com']);
    $demoUser = User::factory()->create(['email' => 'demo@moadian.test']);
    $demoAdmin = User::factory()->admin()->create(['email' => 'admin@moadian.test']);
    $migration = require database_path('migrations/2026_08_15_193812_purge_seeded_demo_accounts.php');

    $migration->up();

    $this->assertModelExists($realUser);
    $this->assertModelMissing($demoUser);
    $this->assertModelMissing($demoAdmin);
    expect($realUser->refresh()->remember_token)->toBeNull();
});

it('does not create demo data when the database seeder runs', function () {
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

it('authenticates an active licensed user', function () {
    $user = User::factory()->create(['email' => 'active@example.com']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('redirects an expired user to the license message', function () {
    $user = User::factory()->expired()->create(['email' => 'expired@example.com']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('license.expired'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $this->post(route('login.store'), [
        'email' => 'unknown@example.com',
        'password' => 'incorrect-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
