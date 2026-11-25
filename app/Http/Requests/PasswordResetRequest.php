<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 교적 비밀번호 재설정 링크 요청 (requestPasswordReset) — 로그인 화면 「비밀번호 찾기」.
 * 이메일 형식만 검사한다. 가입 여부는 어떤 경우에도 응답으로 알려주지 않는다.
 */
class PasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255', 'email:rfc'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => '이메일 주소를 입력해주세요.',
            'email.email'    => '올바른 이메일 형식을 입력해주세요.',
            'email.max'      => '올바른 이메일 형식을 입력해주세요.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
