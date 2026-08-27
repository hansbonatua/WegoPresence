<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nip' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes'],
        ];
    }
}
