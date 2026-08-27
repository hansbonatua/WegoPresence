<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id', Rule::in($this->assignableRoleIds())],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'nip' => ['required', 'string', 'max:20', 'unique:users,nip'],
            'name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'join_date' => ['required', 'date'],
            'city' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['pending', 'active', 'rejected'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * The role ids the authenticated user is allowed to assign.
     *
     * @return array<int, int>
     */
    private function assignableRoleIds(): array
    {
        if ($this->user()?->isSuperAdmin()) {
            return Role::pluck('id')->all();
        }

        return array_values(array_filter([Role::where('name', 'user')->value('id')]));
    }
}
