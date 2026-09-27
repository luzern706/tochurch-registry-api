<?php

namespace App\Http\Requests;

use App\Constants\MemberStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 교인(Member) 관련 요청 유효성 검사
 *
 * Controller 액션 메서드명에 따라 룰을 분기한다.
 *  - getList  : 목록 조회 필터
 *  - getDetail: 상세 조회
 *  - register : 신규 등록
 *  - update   : 정보 수정
 *  - delete   : 삭제
 */
class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'checkDuplicate' => $this->duplicateRules(),
            'sendInvite'     => ['member_id' => ['required', 'integer', 'min:1']],
            'uploadProfileImage' => ['file' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120']],
            'getList'   => $this->listRules(),
            'getDetail' => $this->detailRules(),
            'register'  => $this->registerRules(),
            'update'    => $this->updateRules(),
            'delete'    => $this->deleteRules(),
            default     => [],
        };
    }

    public function messages(): array
    {
        return [
            'required'  => ':attribute 값을 입력해주세요.',
            'email'     => '이메일 형식이 올바르지 않습니다.',
            'integer'   => ':attribute 은(는) 정수여야 합니다.',
            'date'      => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'in'        => ':attribute 값이 허용된 값이 아닙니다.',
            'min'       => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'       => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email'     => '이메일',
            'password'  => '비밀번호',
            'name'      => '이름',
            'phone'     => '휴대전화',
            'member_id' => '교인 ID',
            'page'      => '페이지',
            'size'      => '페이지 크기',
            'file'      => '프로필 사진',
        ];
    }

    private function listRules(): array
    {
        return [
            'keyword'     => ['nullable', 'string', 'max:100'],
            'search_type' => ['nullable', 'in:name,phone,email'],
            'status'      => ['nullable', 'in:' . implode(',', MemberStatus::all())],
            'sort_by'     => ['nullable', 'in:created_at,name,birth_date'],
            'org_ids'     => ['nullable', 'array'],
            'org_ids.*'   => ['integer', 'min:1'],
            'page'        => ['nullable', 'integer', 'min:1'],
            'size'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function detailRules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function registerRules(): array
    {
        return [
            'email'    => ['nullable', 'email', 'max:100'],
            'name'     => ['required', 'string', 'max:50'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'gender'   => ['nullable', 'in:M,F'],
            'birth_date'     => ['nullable', 'date'],
            'birth_type'     => ['nullable', 'in:solar,lunar'],
            'address_zip'    => ['nullable', 'string', 'max:10'],
            'address_main'   => ['nullable', 'string', 'max:200'],
            'address_detail' => ['nullable', 'string', 'max:100'],
            'profile_image'  => ['nullable', 'string', 'max:500'],
            'status'         => ['nullable', 'in:' . implode(',', MemberStatus::registerable())],
            'memo'           => ['nullable', 'string'],
            'churchero_user_id' => ['nullable', 'integer', 'min:1'],

            'member_type'        => ['nullable', 'string', 'max:30'],
            'member_type_source' => ['nullable', 'in:system,user'],
            'position'           => ['nullable', 'string', 'max:30'],
            'baptism_grade'      => ['nullable', 'string', 'max:20'],
            'attendance_grade'   => ['nullable', 'string', 'max:10'],
            'ordained_at'        => ['nullable', 'date'],
            'ordained_church'    => ['nullable', 'string', 'max:100'],
            'baptism_at'         => ['nullable', 'date'],
            'baptism_church'     => ['nullable', 'string', 'max:100'],
            'registered_at'      => ['nullable', 'date'],
            'registration_type'  => ['nullable', 'in:new,transfer,existing'],
            'welcomed_at'        => ['nullable', 'date'],
            'previous_church'    => ['nullable', 'string', 'max:100'],
            'leader_member_id'   => ['nullable', 'integer', 'min:1'],
            'marriage_status'    => ['nullable', 'in:single,married,widowed,divorced'],
            'is_household_head'  => ['nullable', 'boolean'],
            'household_relation' => ['nullable', 'string', 'max:20'],
            'workplace'          => ['nullable', 'string', 'max:100'],
            'occupation'         => ['nullable', 'string', 'max:100'],
            'organization_id'          => ['nullable', 'integer', 'min:1'],
            'household_head_member_id' => ['nullable', 'integer', 'min:1'],
            'custom_field_1'     => ['nullable', 'string', 'max:200'],
            'custom_field_2'     => ['nullable', 'string', 'max:200'],
            'custom_field_3'     => ['nullable', 'string', 'max:200'],
        ];
    }

    private function updateRules(): array
    {
        // 등록 전용 파라미터(부서 매핑·세대주 가족 생성)는 수정에서 받지 않음 — 상세 화면의 조직·가족 API 사용
        $rules = $this->registerRules();
        unset($rules['organization_id'], $rules['household_head_member_id']);
        // 수정은 이전·제적·소천까지 전체 상태 변경 허용
        $rules['status'] = ['nullable', 'in:' . implode(',', MemberStatus::all())];

        return array_merge(
            ['member_id' => ['required', 'integer', 'min:1']],
            array_map(fn ($r) => $this->makeOptional($r), $rules)
        );
    }

    private function duplicateRules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:50'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
        ];
    }

    private function deleteRules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * register 룰에서 'required' 를 'nullable' 로 치환하여 update 용 룰 생성
     */
    private function makeOptional(array $rules): array
    {
        $out = [];
        $hasNullable = false;
        foreach ($rules as $rule) {
            if ($rule === 'required') {
                $out[] = 'nullable';
                $hasNullable = true;
            } else {
                $out[] = $rule;
            }
        }
        if (!$hasNullable && !in_array('nullable', $out, true)) {
            array_unshift($out, 'sometimes');
        }
        return $out;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
