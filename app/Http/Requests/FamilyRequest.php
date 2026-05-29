<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 가족 관계(Family) 관련 요청 유효성 검사
 *
 * 액션별 룰 분기:
 *  - getList  : 특정 교인의 가족 목록
 *  - register : 가족 관계 등록 (양방향 자동 생성)
 *  - update   : 가족 관계 수정 (양방향 동기화)
 *  - delete   : 가족 관계 삭제 (양방향)
 */
class FamilyRequest extends FormRequest
{
    private const RELATION_TYPES = ['배우자', '부모', '자녀', '형제자매', '기타'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'  => $this->listRules(),
            'register' => $this->registerRules(),
            'update'   => $this->updateRules(),
            'delete'   => $this->deleteRules(),
            default    => [],
        };
    }

    public function attributes(): array
    {
        return [
            'member_id'         => '교인 ID',
            'related_member_id' => '관련 교인 ID',
            'relation_type'     => '가족 관계',
            'family_note'       => '가족 특이사항',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'member_id'         => ['required', 'integer', 'min:1'],
            'related_member_id' => ['required', 'integer', 'min:1', 'different:member_id'],
            'relation_type'     => ['required', 'string', 'in:' . implode(',', self::RELATION_TYPES)],
            'family_note'       => ['nullable', 'string'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'member_id'         => ['required', 'integer', 'min:1'],
            'related_member_id' => ['required', 'integer', 'min:1', 'different:member_id'],
            'relation_type'     => ['sometimes', 'string', 'in:' . implode(',', self::RELATION_TYPES)],
            'family_note'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    private function deleteRules(): array
    {
        return [
            'member_id'         => ['required', 'integer', 'min:1'],
            'related_member_id' => ['required', 'integer', 'min:1', 'different:member_id'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
