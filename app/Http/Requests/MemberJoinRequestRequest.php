<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MemberJoinRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getJoinRequestList' => $this->listRules(),
            'holdJoinRequest'    => $this->statusRules(),
            'rejectJoinRequest'  => $this->statusRules(),
            default              => [],
        };
    }

    public function attributes(): array
    {
        return [
            'account_no' => '계정 ID',
            'reason'     => '사유',
            'keyword'    => '검색어',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'string'   => ':attribute 은(는) 문자열이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'page'    => ['nullable', 'integer', 'min:1'],
            'size'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function statusRules(): array
    {
        return [
            'account_no' => ['required', 'integer', 'min:1'],
            'reason'     => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
