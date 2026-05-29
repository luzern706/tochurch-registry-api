<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VisitRequest extends FormRequest
{
    private const VISIT_TYPES = ['annual', 'event'];

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
            'visit_id'          => '심방 ID',
            'member_id'         => '대상 교인 ID',
            'visitor_member_id' => '심방자 교인 ID',
            'manager_member_id' => '담당자 교인 ID',
            'visit_date'        => '심방일',
            'content'           => '심방 내용',
            'visit_type'        => '심방 유형',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'date'     => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'after_or_equal' => ':attribute 은(는) :date 이후여야 합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'member_id'         => ['nullable', 'integer', 'min:1'],
            'visitor_member_id' => ['nullable', 'integer', 'min:1'],
            'visit_type'        => ['nullable', 'string', 'in:' . implode(',', self::VISIT_TYPES)],
            'from_date'         => ['nullable', 'date'],
            'to_date'           => ['nullable', 'date', 'after_or_equal:from_date'],
            'keyword'           => ['nullable', 'string', 'max:100'],
            'page'              => ['nullable', 'integer', 'min:1'],
            'size'              => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'visit_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'member_id'         => ['required', 'integer', 'min:1'],
            'visit_type'        => ['nullable', 'string', 'in:' . implode(',', self::VISIT_TYPES)],
            'event_reason'      => ['nullable', 'string', 'max:30'],
            'visit_date'        => ['required', 'date'],
            'visitor_member_id' => ['required', 'integer', 'min:1'],
            'manager_member_id' => ['nullable', 'integer', 'min:1'],
            'place'             => ['nullable', 'string', 'max:100'],
            'companion'         => ['nullable', 'string', 'max:200'],
            'attendee_count'    => ['nullable', 'integer', 'min:0'],
            'content'           => ['required', 'string'],
            'common_note'       => ['nullable', 'string'],
            'private_note'      => ['nullable', 'string'],
            'add_to_prayer'     => ['nullable', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'visit_id'          => ['required', 'integer', 'min:1'],
            'member_id'         => ['sometimes', 'integer', 'min:1'],
            'visit_type'        => ['sometimes', 'string', 'in:' . implode(',', self::VISIT_TYPES)],
            'event_reason'      => ['sometimes', 'nullable', 'string', 'max:30'],
            'visit_date'        => ['sometimes', 'date'],
            'visitor_member_id' => ['sometimes', 'integer', 'min:1'],
            'manager_member_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'place'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'companion'         => ['sometimes', 'nullable', 'string', 'max:200'],
            'attendee_count'    => ['sometimes', 'nullable', 'integer', 'min:0'],
            'content'           => ['sometimes', 'string'],
            'common_note'       => ['sometimes', 'nullable', 'string'],
            'private_note'      => ['sometimes', 'nullable', 'string'],
            'add_to_prayer'     => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
