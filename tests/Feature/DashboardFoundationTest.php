<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a guest can view the login page but is redirected there from the dashboard', function () {
    $this->get(route('login'))->assertOk();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('an authenticated user can access the dashboard, log out, and no longer access it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
