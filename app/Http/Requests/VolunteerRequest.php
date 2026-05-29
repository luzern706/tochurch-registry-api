<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VolunteerRequest extends FormRequest
{
    private const MODES = ['regular', 'onetime'];
    private const FREQUENCIES = ['weekly', 'biweekly', 'monthly'];
    private const PARTICIPATION_TYPES = ['application', 'assignment'];
    private const STATUSES = ['active', 'inactive'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList'             => $this->teamListRules(),
            'getDetail'           => $this->teamKeyRules(),
            'register'            => $this->registerTeamRules(),
            'update'              => $this->updateTeamRules(),
            'delete'              => $this->teamKeyRules(),
            'assignVolunteer'     => $this->assignVolunteerRules(),
            'unassignVolunteer'   => $this->mappingKeyRules(),
            'getVolunteersByTeam' => $this->getVolunteersByTeamRules(),
            'getTeamsByMember'    => $this->getTeamsByMemberRules(),
            default               => [],
        };
    }

    public function attributes(): array
    {
        return [
            'team_id'           => '봉사 팀 ID',
            'member_id'         => '교인 ID',
            'manager_member_id' => '담당자 교인 ID',
            'name'              => '봉사명',
            'required_count'    => '필요 인원',
            'role'              => '역할',
            'joined_at'         => '시작일',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'date'     => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'date_format' => ':attribute 형식이 올바르지 않습니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
            'between'  => ':attribute 은(는) :min ~ :max 사이여야 합니다.',
        ];
    }

    private function teamListRules(): array
    {
        return [
            'service_type'      => ['nullable', 'string', 'max:30'],
            'mode'              => ['nullable', 'in:' . implode(',', self::MODES)],
            'is_active'         => ['nullable', 'boolean'],
            'manager_member_id' => ['nullable', 'integer', 'min:1'],
            'keyword'           => ['nullable', 'string', 'max:100'],
            'page'              => ['nullable', 'integer', 'min:1'],
            'size'              => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function teamKeyRules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function teamFields(bool $forUpdate): array
    {
        $rules = [
            'name'               => ['string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:300'],
            'service_type'       => ['nullable', 'string', 'max:30'],
            'mode'               => ['nullable', 'in:' . implode(',', self::MODES)],
            'frequency'          => ['nullable', 'in:' . implode(',', self::FREQUENCIES)],
            'day_of_week'        => ['nullable', 'integer', 'between:0,6'],
            'service_time'       => ['nullable', 'date_format:H:i:s'],
            'event_date'         => ['nullable', 'date'],
            'start_time'         => ['nullable', 'date_format:H:i:s'],
            'end_time'           => ['nullable', 'date_format:H:i:s'],
            'required_count'     => ['nullable', 'integer', 'min:1'],
            'manager_member_id'  => ['nullable', 'integer', 'min:1'],
            'participation_type' => ['nullable', 'in:' . implode(',', self::PARTICIPATION_TYPES)],
            'is_active'          => ['nullable', 'boolean'],
        ];

        // register: name required / update: 모든 필드 sometimes
        if ($forUpdate) {
            $out = [];
            foreach ($rules as $k => $v) {
                array_unshift($v, 'sometimes');
                $out[$k] = $v;
            }
            return $out;
        }

        array_unshift($rules['name'], 'required');
        return $rules;
    }

    private function registerTeamRules(): array
    {
        return $this->teamFields(false);
    }

    private function updateTeamRules(): array
    {
        return array_merge(
            ['team_id' => ['required', 'integer', 'min:1']],
            $this->teamFields(true)
        );
    }

    private function assignVolunteerRules(): array
    {
        return [
            'team_id'   => ['required', 'integer', 'min:1'],
            'member_id' => ['required', 'integer', 'min:1'],
            'role'      => ['nullable', 'string', 'max:50'],
            'joined_at' => ['nullable', 'date'],
            'status'    => ['nullable', 'in:' . implode(',', self::STATUSES)],
        ];
    }

    private function mappingKeyRules(): array
    {
        return [
            'team_id'   => ['required', 'integer', 'min:1'],
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function getVolunteersByTeamRules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'min:1'],
            'status'  => ['nullable', 'in:' . implode(',', self::STATUSES)],
            'page'    => ['nullable', 'integer', 'min:1'],
            'size'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function getTeamsByMemberRules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
