<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 교적 비밀번호 설정 링크 (checkSetupToken / setupPassword)
 * 비밀번호 규칙은 내정보 비밀번호 변경(ProfileRequest changePassword)과 동일.
 */
class SetupPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $path = $this->path();
        $pos = strrpos($path, '/');
        $last = strtolower($pos === false ? $path : substr($path, $pos + 1));

        if ($last === 'setuppassword') {
            return [
                'token'        => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
                'new_password' => [
                    'required', 'string', 'min:8', 'max:255', 'confirmed',
                    'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
                ],
            ];
        }

        return [
            'token' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'            => '링크가 올바르지 않습니다.',
            'token.size'                => '링크가 올바르지 않습니다.',
            'token.regex'               => '링크가 올바르지 않습니다.',
            'new_password.required'     => '새 비밀번호를 입력해주세요.',
            'new_password.min'          => '새 비밀번호는 8자 이상이어야 합니다.',
            'new_password.regex'        => '새 비밀번호는 영문/숫자/특수문자를 모두 포함해야 합니다.',
            'new_password.confirmed'    => '새 비밀번호 확인이 일치하지 않습니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // 토큰 형식 오류는 존재 여부를 알려주지 않도록 토큰 무효와 동일하게 응답
        if ($validator->errors()->has('token')) {
            throw new HttpResponseException(
                ApiResponse::fail('SETUP_TOKEN_INVALID', '링크가 만료되었거나 이미 사용되었습니다.', 400)
            );
        }
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
