<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FinanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getMonthly' => [
                'year'  => ['required', 'integer', 'min:2000', 'max:2100'],
                'month' => ['required', 'integer', 'min:1', 'max:12'],
            ],
            'getAnnual' => [
                'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'year'  => '연도',
            'month' => '월',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 값을 입력해주세요.',
            'integer'  => ':attribute 은(는) 정수여야 합니다.',
            'min'      => ':attribute 은(는) 최소 :min 이상이어야 합니다.',
            'max'      => ':attribute 은(는) 최대 :max 까지 입력 가능합니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
