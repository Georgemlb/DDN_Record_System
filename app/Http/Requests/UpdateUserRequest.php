<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $targetUser = $this->route('user');

        return $targetUser instanceof User && ($this->user()?->can('update', $targetUser) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $targetUser */
        $targetUser = $this->route('user');
        $isTechAdmin = $this->user()?->isTechAdmin() ?? false;
        $allowedRoles = $isTechAdmin
            ? User::ROLES
            : [User::ROLE_ADMIN, User::ROLE_STAFF];

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUser->id)],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => ['nullable', 'string', Password::defaults()],
        ];
    }
}
