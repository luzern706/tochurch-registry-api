<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FinanceAccountRequest extends FormRequest
{
    private const TYPES = ['현금', '보통예금', '적금', '예치금'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'       => $this->listRules(),
            'getActiveList' => [
                'usage' => ['required', 'in:offering,expense'],
            ],
            'register' => $this->registerRules(),
            'update'   => $this->updateRules(),
            'delete'   => $this->keyRules(),
            default    => [],
        };
    }

    public function attributes(): array
    {
        return [
            'account_id'       => '계좌 ID',
            'type'             => '유형',
            'name'             => '계좌명',
            'bank'             => '은행',
            'account_number'   => '계좌번호',
            'description'      => '설명',
            'use_for_offering' => '헌금 입력 사용',
            'use_for_expense'  => '지출 입력 사용',
            'is_active'        => '활성',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'type'      => ['nullable', 'in:' . implode(',', self::TYPES)],
            'bank'      => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'keyword'   => ['nullable', 'string', 'max:100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'type'             => ['required', 'in:' . implode(',', self::TYPES)],
            'name'             => ['required', 'string', 'max:100'],
            'bank'             => ['nullable', 'string', 'max:50'],
            'account_number'   => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:200'],
            'use_for_offering' => ['nullable', 'boolean'],
            'use_for_expense'  => ['nullable', 'boolean'],
            'is_active'        => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'account_id'       => ['required', 'integer', 'min:1'],
            'type'             => ['sometimes', 'in:' . implode(',', self::TYPES)],
            'name'             => ['sometimes', 'string', 'max:100'],
            'bank'             => ['sometimes', 'nullable', 'string', 'max:50'],
            'account_number'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'description'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'use_for_offering' => ['sometimes', 'boolean'],
            'use_for_expense'  => ['sometimes', 'boolean'],
            'is_active'        => ['sometimes', 'boolean'],
            'sort_order'       => ['sometimes', 'integer'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
