<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => 'required|string|max:50',
            'email' => [
                'required', 'email',
                Rule::unique('users')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'password' => 'required|string|min:6',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position' => 'nullable|string|max:50',
        ];
    }
}
