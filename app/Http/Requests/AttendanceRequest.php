<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AttendanceRequest extends FormRequest
{
    private const STATUSES = ['present', 'absent', 'unknown'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'recordBulk'      => $this->recordBulkRules(),
            'getListByService'=> $this->getListByServiceRules(),
            'getListByMember' => $this->getListByMemberRules(),
            default           => [],
        };
    }

    public function attributes(): array
    {
        return [
            'service_id'      => '예배 ID',
            'attend_date'     => '출석 날짜',
            'records'         => '출석 목록',
            'organization_id' => '조직 ID',
            'member_id'       => '교인 ID',
            'from_date'       => '시작일',
            'to_date'         => '종료일',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'date'     => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'array'    => ':attribute 은(는) 배열이어야 합니다.',
            'in'       => ':attribute 값이 허용된 값이 아닙니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    private function recordBulkRules(): array
    {
        return [
            'service_id'         => ['required', 'integer', 'min:1'],
            'attend_date'        => ['required', 'date'],
            'records'            => ['required', 'array', 'min:1', 'max:500'],
            'records.*.member_id'=> ['required', 'integer', 'min:1'],
            'records.*.status'   => ['required', 'string', 'in:' . implode(',', self::STATUSES)],
            'records.*.note'     => ['nullable', 'string'],
        ];
    }

    private function getListByServiceRules(): array
    {
        return [
            'service_id'      => ['required', 'integer', 'min:1'],
            'attend_date'     => ['required', 'date'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function getListByMemberRules(): array
    {
        return [
            'member_id'  => ['required', 'integer', 'min:1'],
            'from_date'  => ['nullable', 'date'],
            'to_date'    => ['nullable', 'date', 'after_or_equal:from_date'],
            'service_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
