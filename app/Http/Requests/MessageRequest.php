<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MessageRequest extends FormRequest
{
    private const SEND_TYPES = ['sms', 'push', 'email'];
    private const TARGET_TYPES = ['all', 'organization', 'members'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'   => $this->listRules(),
            'getDetail' => $this->keyRules(),
            'send'      => $this->sendRules(),
            'delete'    => $this->keyRules(),
            default     => [],
        };
    }

    public function attributes(): array
    {
        return [
            'message_id'      => '메시지 ID',
            'title'           => '제목',
            'content'         => '내용',
            'send_type'       => '발송 유형',
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
            'date'        => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'in'          => ':attribute 값이 허용된 값이 아닙니다.',
            'min'         => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'         => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'send_type' => ['nullable', 'in:' . implode(',', self::SEND_TYPES)],
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date', 'after_or_equal:from_date'],
            'keyword'   => ['nullable', 'string', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'size'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'message_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function sendRules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:200'],
            'content'         => ['required', 'string'],
            'send_type'       => ['nullable', 'in:' . implode(',', self::SEND_TYPES)],
            'target_type'     => ['required', 'in:' . implode(',', self::TARGET_TYPES)],
            'organization_id' => ['required_if:target_type,organization', 'nullable', 'integer', 'min:1'],
            'member_ids'      => ['required_if:target_type,members', 'nullable', 'array', 'min:1', 'max:1000'],
            'member_ids.*'    => ['integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
