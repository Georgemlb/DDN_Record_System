<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of system users.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->orderByRaw("CASE role WHEN 'tech_admin' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('users/Index', [
            'users' => $users,
            'canManageTechAdmin' => auth()->user()?->isTechAdmin() ?? false,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        $isTechAdmin = auth()->user()?->isTechAdmin() ?? false;
        $allowedRoles = $isTechAdmin
            ? User::ROLES
            : [User::ROLE_ADMIN, User::ROLE_STAFF];

        return Inertia::render('users/Create', [
            'roles' => $allowedRoles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $isTechAdmin = auth()->user()?->isTechAdmin() ?? false;
        $allowedRoles = $isTechAdmin
            ? User::ROLES
            : [User::ROLE_ADMIN, User::ROLE_STAFF];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "User {$validated['name']} created successfully.",
        ]);

        return to_route('users.index');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        $isTechAdmin = auth()->user()?->isTechAdmin() ?? false;
        $allowedRoles = $isTechAdmin
            ? User::ROLES
            : [User::ROLE_ADMIN, User::ROLE_STAFF];

        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $allowedRoles,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $isTechAdmin = auth()->user()?->isTechAdmin() ?? false;
        $allowedRoles = $isTechAdmin
            ? User::ROLES
            : [User::ROLE_ADMIN, User::ROLE_STAFF];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'role' => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "User {$user->name} updated successfully.",
        ]);

        return to_route('users.index');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $userName = $user->name;
        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "User {$userName} deleted successfully.",
        ]);

        return to_route('users.index');
    }
}
