<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getMemberStats'     => [],
            'getAttendanceStats', 'getAttendanceStatsByOrg',
            'getStatsByService', 'getMemberRateDistribution' => $this->dateRangeRules() + [
                'service_id'      => ['nullable', 'integer', 'min:1'],
                'organization_id' => ['nullable', 'integer', 'min:1'],
            ],
            'getOfferingStats'   => $this->dateRangeRules() + [
                'category' => ['nullable', 'string', 'max:50'],
            ],
            'getVisitStats'      => $this->dateRangeRules() + [
                'visitor_member_id' => ['nullable', 'integer', 'min:1'],
            ],
            'getDashboard'       => [],
            'getFinanceDashboard' => [],
            'getFinanceStats'    => $this->dateRangeRules(),
            default              => [],
        };
    }

    public function attributes(): array
    {
        return [
            'from_date'         => '시작일',
            'to_date'           => '종료일',
            'service_id'        => '예배 ID',
            'organization_id'   => '조직 ID',
            'visitor_member_id' => '심방자 교인 ID',
            'category'          => '헌금 항목',
        ];
    }

    public function messages(): array
    {
        return [
            'required'       => ':attribute 값을 입력해주세요.',
            'integer'        => ':attribute 은(는) 정수여야 합니다.',
            'date'           => ':attribute 은(는) 날짜 형식이어야 합니다.',
            'after_or_equal' => ':attribute 은(는) :date 이후여야 합니다.',
            'min'            => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
        ];
    }

    private function dateRangeRules(): array
    {
        return [
            'from_date' => ['required', 'date'],
            'to_date'   => ['required', 'date', 'after_or_equal:from_date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
