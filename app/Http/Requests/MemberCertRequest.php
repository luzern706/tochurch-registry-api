<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MemberCertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList' => [
                'status'  => ['nullable', 'string', 'in:pending,approved,rejected,released'],
                'keyword' => ['nullable', 'string', 'max:100'],
                'page'    => ['nullable', 'integer', 'min:1'],
                'size'    => ['nullable', 'integer', 'min:1', 'max:100'],
            ],
            'approve' => [
                'account_nos'   => ['required', 'array', 'min:1', 'max:200'],
                'account_nos.*' => ['required', 'integer', 'min:1'],
            ],
            'reject' => [
                'account_no' => ['required', 'integer', 'min:1'],
                'reason'     => ['nullable', 'string', 'max:300'],
            ],
            'reset' => [
                'account_no' => ['required', 'integer', 'min:1'],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'status'        => '상태',
            'keyword'       => '검색어',
            'account_nos'   => '대상 성도',
            'account_nos.*' => '대상 성도',
            'account_no'    => '대상 성도',
            'reason'        => '사유',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'string'   => ':attribute 은(는) 문자열이어야 합니다.',
            'array'    => ':attribute 은(는) 목록이어야 합니다.',
            'in'       => ':attribute 값이 올바르지 않습니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
