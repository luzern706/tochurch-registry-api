<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'saveRole' => [
                'role'                      => ['required', 'string', 'in:pastor,minister,volunteer'],
                'permissions'               => ['required', 'array', 'min:1'],
                'permissions.*.page_code'   => ['required', 'string'],
                'permissions.*.can_access'  => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'role.required' => '역할을 선택해주세요.',
            'role.in'       => '변경할 수 없는 역할입니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
