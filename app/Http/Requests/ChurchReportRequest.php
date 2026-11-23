<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** 교회 게시글 신고함 요청 검증 — 두레이 교회게시판 관리분리 ④ */
class ChurchReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'getList' => [
                'tab'  => ['nullable', 'in:pending,done'],
                'page' => ['nullable', 'integer', 'min:1'],
                'size' => ['nullable', 'integer', 'min:1', 'max:100'],
            ],
            'process' => [
                'seq'    => ['required', 'integer', 'min:1'],
                'action' => ['required', 'in:hide,delete,dismiss'],
                'reason' => ['required', 'string', 'min:2', 'max:200'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'reason.required' => '처리 사유를 입력해 주세요.',
            'reason.min'      => '처리 사유를 2자 이상 입력해 주세요.',
            'reason.max'      => '처리 사유는 200자까지 입력할 수 있습니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 422)
        );
    }
}
