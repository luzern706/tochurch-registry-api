<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {
            'updateMyProfile' => [
                'name'  => ['sometimes', 'string', 'max:50'],
                'email' => ['sometimes', 'nullable', 'email', 'max:100'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            ],
            'changePassword' => [
                'current_password' => ['required', 'string'],
                'new_password'      => [
                    'required', 'string', 'min:8', 'max:255', 'confirmed',
                    'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
                ],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'name.max'                  => '이름은 50자 이하여야 합니다.',
            'email.email'               => '올바른 이메일 형식이 아닙니다.',
            'email.max'                 => '이메일은 100자 이하여야 합니다.',
            'phone.max'                 => '휴대폰 번호는 20자 이하여야 합니다.',
            'current_password.required' => '현재 비밀번호를 입력해주세요.',
            'new_password.required'     => '새 비밀번호를 입력해주세요.',
            'new_password.min'          => '새 비밀번호는 8자 이상이어야 합니다.',
            'new_password.regex'        => '새 비밀번호는 영문/숫자/특수문자를 모두 포함해야 합니다.',
            'new_password.confirmed'    => '새 비밀번호 확인이 일치하지 않습니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
