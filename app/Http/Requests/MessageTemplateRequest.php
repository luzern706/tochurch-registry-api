<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MessageTemplateRequest extends FormRequest
{
    private const CATEGORIES = ['worship', 'newcomer', 'attendance', 'visitation', 'education', 'service', 'urgent', 'other'];
    private const MSG_TYPES  = ['SMS', 'LMS'];
    private const SCOPES     = ['public', 'private'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'       => $this->listRules(),
            'getDetail'     => $this->keyRules(),
            'register'      => $this->saveRules(),
            'update'        => array_merge($this->keyRules(), $this->saveRules(true)),
            'duplicate'     => $this->keyRules(),
            'toggleActive'  => array_merge($this->keyRules(), ['is_active' => ['required', 'boolean']]),
            default         => [],
        };
    }

    public function attributes(): array
    {
        return [
            'id'       => '템플릿 ID',
            'name'     => '템플릿명',
            'category' => '분류',
            'content'  => '본문',
            'keyword'  => '검색어',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'string'   => ':attribute 은(는) 문자열이어야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'boolean'  => ':attribute 은(는) true/false 값이어야 합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'keyword'   => ['nullable', 'string', 'max:100'],
            'category'  => ['nullable', 'in:' . implode(',', self::CATEGORIES)],
            'msg_type'  => ['nullable', 'in:' . implode(',', self::MSG_TYPES)],
            'scope'     => ['nullable', 'in:' . implode(',', self::SCOPES)],
            'is_active' => ['nullable', 'boolean'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'size'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function keyRules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function saveRules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        return [
            'name'          => [$req, 'string', 'max:100'],
            'category'      => [$req, 'in:' . implode(',', self::CATEGORIES)],
            'msg_type'      => ['nullable', 'in:' . implode(',', self::MSG_TYPES)],
            'scope'         => ['nullable', 'in:' . implode(',', self::SCOPES)],
            'content'       => [$req, 'string'],
            'unsub_enabled' => ['nullable', 'boolean'],
            'unsub_text'    => ['nullable', 'string', 'max:200'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
