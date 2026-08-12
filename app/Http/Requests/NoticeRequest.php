<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NoticeRequest extends FormRequest
{
    private const TYPES = ['notice', 'update', 'check', 'guide'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'   => ['type' => ['nullable', 'in:' . implode(',', self::TYPES)]],
            'getDetail' => ['notice_no' => ['required', 'integer', 'min:1']],
            default     => [],
        };
    }

    public function attributes(): array
    {
        return ['notice_no' => '공지 ID'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
