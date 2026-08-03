<?php

use App\Models\User;

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
