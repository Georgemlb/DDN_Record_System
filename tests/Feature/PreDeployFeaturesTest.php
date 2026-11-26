<?php

use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('root url redirects guests to login and authenticated users to dashboard', function () {
    $this->get('/')
        ->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('security headers are applied to responses', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('authenticated user can change their own password', function () {
    $user = User::factory()->create([
        'password' => 'OldPassword123!',
    ]);

    $this->actingAs($user)
        ->get('/account/password')
        ->assertOk();

    $this->actingAs($user)
        ->put('/account/password', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->put('/account/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewStrongPass123!', $user->fresh()->password))->toBeTrue();
});

test('activity logs are restricted to admins and record mutations', function () {
    $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($staff)
        ->get('/activity-logs')
        ->assertForbidden();

    $transaction = Transaction::factory()->create([
        'user_id' => $admin->id,
        'customer_name' => 'Maria Santos',
    ]);

    $this->actingAs($admin)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $log = ActivityLog::query()->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->module)->toBe('transaction')
        ->and($log->action)->toBe('deleted')
        ->and($log->user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->get('/activity-logs')
        ->assertOk();
});
