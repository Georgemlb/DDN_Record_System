<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('tech admin can view users and create any role', function () {
    $techAdmin = User::factory()->techAdmin()->create();

    $this->actingAs($techAdmin)->get(route('users.index'))->assertOk();

    $response = $this->actingAs($techAdmin)->post(route('users.store'), [
        'name' => 'New Tech Admin',
        'username' => 'new_tech',
        'email' => 'newtech@ddn.com',
        'role' => User::ROLE_TECH_ADMIN,
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', [
        'username' => 'new_tech',
        'role' => User::ROLE_TECH_ADMIN,
    ]);
});

test('admin can view users and create staff and admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get(route('users.index'))->assertOk();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'New Staff Member',
        'username' => 'staff_one',
        'role' => User::ROLE_STAFF,
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', [
        'username' => 'staff_one',
        'role' => User::ROLE_STAFF,
    ]);
});

test('admin cannot create a tech admin user', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Unauthorized Tech',
        'username' => 'unauth_tech',
        'role' => User::ROLE_TECH_ADMIN,
        'password' => 'secret123',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertDatabaseMissing('users', ['username' => 'unauth_tech']);
});

test('admin cannot edit or delete a tech admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $techAdmin = User::factory()->techAdmin()->create();

    $this->actingAs($admin)
        ->get(route('users.edit', $techAdmin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('users.update', $techAdmin), [
            'name' => 'Hacked Name',
            'username' => $techAdmin->username,
            'role' => User::ROLE_STAFF,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $techAdmin))
        ->assertForbidden();
});

test('user cannot delete their own account', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('staff is forbidden from accessing user management', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('users.create'))->assertForbidden();
});

test('user can log in using their username', function () {
    $user = User::factory()->create([
        'username' => 'cashier_juan',
        'password' => Hash::make('mypassword123'),
        'role' => User::ROLE_STAFF,
    ]);

    $response = $this->post('/login', [
        'username' => 'cashier_juan',
        'password' => 'mypassword123',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});
