<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 조직(Organization) 관련 요청 유효성 검사
 *
 * 액션별 룰 분기:
 *  - getList            : 조직 목록
 *  - getDetail          : 조직 상세
 *  - register / update  : 조직 등록/수정
 *  - delete             : 조직 삭제
 *  - assignMember       : 교인-조직 매핑 생성/갱신
 *  - unassignMember     : 교인-조직 매핑 해제
 *  - getMembersByOrg    : 조직 소속 교인 목록
 */
class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'          => $this->listRules(),
            'getDetail'        => $this->detailRules(),
            'register'         => $this->registerRules(),
            'update'           => $this->updateRules(),
            'delete'           => $this->detailRules(),
            'assignMember'     => $this->assignMemberRules(),
            'unassignMember'   => $this->mappingKeyRules(),
            'getMembersByOrg'  => $this->getMembersByOrgRules(),
            default            => [],
        };
    }

    public function attributes(): array
    {
        return [
            'organization_id' => '조직 ID',
            'parent_id'       => '상위 조직 ID',
            'member_id'       => '교인 ID',
            'name'            => '조직명',
            'is_primary'      => '주소속 여부',
            'joined_at'       => '가입일',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'date'     => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function listRules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'keyword'   => ['nullable', 'string', 'max:50'],
        ];
    }

    private function detailRules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:50'],
            'parent_id'  => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'min:1'],
            'name'            => ['sometimes', 'string', 'max:50'],
            'parent_id'       => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort_order'      => ['sometimes', 'integer'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    private function assignMemberRules(): array
    {
        return [
            'member_id'       => ['required', 'integer', 'min:1'],
            'organization_id' => ['required', 'integer', 'min:1'],
            'is_primary'      => ['nullable', 'boolean'],
            'joined_at'       => ['nullable', 'date'],
        ];
    }

    private function mappingKeyRules(): array
    {
        return [
            'member_id'       => ['required', 'integer', 'min:1'],
            'organization_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function getMembersByOrgRules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'min:1'],
            'keyword'         => ['nullable', 'string', 'max:100'],
            'page'            => ['nullable', 'integer', 'min:1'],
            'size'            => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
