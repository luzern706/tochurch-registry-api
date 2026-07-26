<?php

namespace App\Http\Requests;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {
            'getAuditLogs' => [
                'menu_code'   => ['sometimes', 'nullable', 'string', 'in:' . implode(',', AuditMenuCode::all())],
                'action_type' => ['sometimes', 'nullable', 'string', 'in:' . implode(',', AuditActionType::all())],
                'admin_no'    => ['sometimes', 'nullable', 'integer'],
                'search_type' => ['sometimes', 'nullable', 'string', 'in:user,target,summary', 'required_with:keyword'],
                'keyword'     => ['sometimes', 'nullable', 'string', 'max:100'],
                'date_from'   => ['sometimes', 'nullable', 'date'],
                'date_to'     => ['sometimes', 'nullable', 'date'],
                'page'        => ['sometimes', 'integer', 'min:1'],
                'size'        => ['sometimes', 'integer', 'min:1', 'max:100'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'menu_code.in'           => '유효하지 않은 메뉴 코드입니다.',
            'action_type.in'         => '유효하지 않은 액션 타입입니다.',
            'search_type.in'         => '검색 대상은 user, target, summary 중 하나여야 합니다.',
            'search_type.required_with' => '검색어를 입력한 경우 검색 대상을 선택해주세요.',
            'keyword.max'            => '검색어는 100자 이하여야 합니다.',
            'date_from.date'         => '조회 시작일 형식이 올바르지 않습니다.',
            'date_to.date'           => '조회 종료일 형식이 올바르지 않습니다.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::fail('VALIDATION_FAILED', $validator->errors()->first(), 400)
        );
    }
}
