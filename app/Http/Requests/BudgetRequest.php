<?php

namespace App\Http\Requests;

use App\Constants\ExpenseCategoryGroup;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getStatus' => $this->getStatusRules(),
            'register'  => $this->registerRules(),
            'update'    => $this->updateRules(),
            'delete'    => $this->keyRules(),
            default     => [],
        };
    }

    public function attributes(): array
    {
        return [
            'budget_id' => '예산 ID',
            'year'      => '예산 연도',
            'category'  => '예산 항목',
            'amount'    => '예산 금액',
            'formula'   => '계산식',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function getStatusRules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'budget_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'year'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'category' => ['required', 'in:' . implode(',', ExpenseCategoryGroup::ORDER)],
            'amount'   => ['required', 'integer', 'min:0'],
            'formula'  => ['nullable', 'string', 'max:200'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'budget_id' => ['required', 'integer', 'min:1'],
            'amount'    => ['sometimes', 'integer', 'min:0'],
            'formula'   => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
