<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PushNotificationRequest extends FormRequest
{
    private const TARGET_TYPES = ['all', 'organization', 'group', 'members'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getTargetSummary' => $this->targetRules(),
            'send'             => $this->sendRules(),
            'getDetail'        => $this->keyRules(),
            'resend'           => $this->resendRules(),
            default            => [],
        };
    }

    public function attributes(): array
    {
        return [
            'message_id'      => '메시지 ID',
            'title'           => '제목',
            'content'         => '내용',
            'target_type'     => '대상 유형',
            'organization_id' => '조직 ID',
            'member_ids'      => '교인 ID 목록',
        ];
    }

    public function messages(): array
    {
        return [
            'required'    => ':attribute 값을 입력해주세요.',
            'required_if' => ':attribute 값을 입력해주세요.',
            'integer'     => ':attribute 은(는) 정수여야 합니다.',
            'array'       => ':attribute 은(는) 배열이어야 합니다.',
            'in'          => ':attribute 값이 허용된 값이 아닙니다.',
            'min'         => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'         => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function targetRules(): array
    {
        return [
            'target_type'         => ['required', 'in:' . implode(',', self::TARGET_TYPES)],
            'organization_id'     => ['required_if:target_type,organization', 'nullable', 'integer', 'min:1'],
            'org_ids'             => ['nullable', 'array'],
            'org_ids.*'           => ['integer', 'min:1'],
            'positions'           => ['nullable', 'array'],
            'positions.*'         => ['string'],
            'member_types'        => ['nullable', 'array'],
            'member_types.*'      => ['string'],
            'baptism_grades'      => ['nullable', 'array'],
            'baptism_grades.*'    => ['string'],
            'member_ids'          => ['required_if:target_type,members', 'nullable', 'array', 'min:1', 'max:1000'],
            'member_ids.*'        => ['integer', 'min:1'],
        ];
    }

    private function sendRules(): array
    {
        return array_merge($this->targetRules(), [
            'title'     => ['required', 'string', 'max:50'],
            'content'   => ['required', 'string', 'max:200'],
            'deep_link' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function keyRules(): array
    {
        return [
            'message_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function resendRules(): array
    {
        return [
            'message_id'        => ['required', 'integer', 'min:1'],
            'include_permanent' => ['nullable', 'boolean'],
            'title'             => ['nullable', 'string', 'max:50'],
            'content'           => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
