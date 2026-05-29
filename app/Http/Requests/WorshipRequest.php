<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'   => $this->listRules(),
            'getDetail' => $this->keyRules(),
            'register'  => $this->registerRules(),
            'update'    => $this->updateRules(),
            'delete'    => $this->keyRules(),
            default     => [],
        };
    }

    public function attributes(): array
    {
        return [
            'service_id'    => '예배 ID',
            'name'          => '예배명',
            'day_of_week'   => '요일',
            'target_org_id' => '대상 조직 ID',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'between'  => ':attribute 은(는) :min ~ :max 사이여야 합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'is_active'   => ['nullable', 'boolean'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'keyword'     => ['nullable', 'string', 'max:50'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:50'],
            'day_of_week'   => ['nullable', 'integer', 'between:0,6'],
            'target_org_id' => ['nullable', 'integer', 'min:1'],
            'sort_order'    => ['nullable', 'integer'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'service_id'    => ['required', 'integer', 'min:1'],
            'name'          => ['sometimes', 'string', 'max:50'],
            'day_of_week'   => ['sometimes', 'nullable', 'integer', 'between:0,6'],
            'target_org_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort_order'    => ['sometimes', 'integer'],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
